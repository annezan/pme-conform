<?php

/**
 * Controleur AscPortefeuilleController — Vue consolidee des clients pour le
 * consultant ASC : portefeuille + vue 360 (traitements + signatures + registres
 * + analyses + plans d'actions).
 *
 * Scope : admin/manager voient tout, consultant voit son portefeuille.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Analyse;
use App\Models\Client;
use App\Models\PlanAction;
use App\Models\RegistreKyc;
use App\Models\Signature;
use App\Models\Traitement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(
    path: "/api/asc/portefeuille",
    get: new OA\Get(
        operationId: "asc-portefeuille-index",
        summary: "Portefeuille clients ASC",
        description: "Vue consolidée des clients pour le consultant ASC avec synthèse 360°",
        tags: ["Portefeuille ASC"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "search",
                in: "query",
                description: "Recherche dans la raison sociale",
                required: false,
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "per_page",
                in: "query",
                description: "Nombre d'éléments par page",
                required: false,
                schema: new OA\Schema(type: "integer", default: 20)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Portefeuille clients avec synthèse",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                type: "object",
                                properties: [
                                    new OA\Property(property: "id", type: "integer"),
                                    new OA\Property(property: "raison_sociale", type: "string"),
                                    new OA\Property(property: "siret", type: "string", nullable: true),
                                    new OA\Property(property: "missions_count", type: "integer"),
                                    new OA\Property(property: "analyses_count", type: "integer"),
                                    new OA\Property(property: "signatures_count", type: "integer"),
                                    new OA\Property(property: "plans_action_count", type: "integer"),
                                    new OA\Property(property: "traitements_count", type: "integer"),
                                    new OA\Property(property: "registres_count", type: "integer"),
                                    new OA\Property(property: "derniere_activite", type: "string", format: "date-time", nullable: true)
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Réservé aux consultants ASC")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/asc/portefeuille/{client_id}/vue-360",
    get: new OA\Get(
        operationId: "asc-portefeuille-vue360",
        summary: "Vue 360° client",
        description: "Vue complète d'un client avec tous ses éléments (traitements, signatures, registres, analyses, plans d'action)",
        tags: ["Portefeuille ASC"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "client_id",
                in: "path",
                description: "ID du client",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Vue 360° complète du client",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "client", type: "object", properties: [
                            new OA\Property(property: "id", type: "integer"),
                            new OA\Property(property: "raison_sociale", type: "string"),
                            new OA\Property(property: "siret", type: "string", nullable: true)
                        ]),
                        new OA\Property(property: "traitements", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "signatures", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "registres", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "analyses", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "plans_action", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Réservé aux consultants ASC"),
            new OA\Response(response: 404, description: "Client non trouvé")
        ]
    )
)]

class AscPortefeuilleController extends Controller
{
    /**
     * Portefeuille : liste des clients avec synthese.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo('view-portefeuille'),
            403,
            'Permission view-portefeuille requise.'
        );

        $query = Client::query()->with('secteursActivite:id,nom');

        // User sans permission de gerer tout le portefeuille : uniquement ses clients assignes.
        if (! $user->hasPermissionTo('view-all-portefeuille')) {
            $clientIds = $user->clients()->pluck('clients.id');
            $query->whereIn('id', $clientIds);
        }

        if ($request->filled('q')) {
            $q = $request->q;
            // Colonne `secteur_activite` supprimee (migration 2026_05_12_210000) ;
            // on cherche dans le pivot client_secteur_activite -> secteurs_activite.nom.
            $query->where(fn ($sub) => $sub
                ->where('raison_sociale', 'ilike', "%{$q}%")
                ->orWhere('sigle', 'ilike', "%{$q}%")
                ->orWhereHas('secteursActivite', fn ($w) => $w->where('nom', 'ilike', "%{$q}%")));
        }
        if ($request->filled('type_structure')) {
            $query->where('type_structure', $request->type_structure);
        }

        $clients = $query->orderBy('raison_sociale')->paginate(20);

        // Enrichir chaque client avec ses stats
        $clients->getCollection()->transform(function (Client $c) {
            return [
                'id' => $c->id,
                'raison_sociale' => $c->raison_sociale,
                'sigle' => $c->sigle,
                'secteurs_activite' => $c->secteursActivite->pluck('nom')->all(),
                'type_structure' => $c->type_structure,
                'statut' => $c->statut,
                'ville' => $c->ville,
                'logo_path' => $c->logo_path,
                'stats' => [
                    'traitements_total' => $c->traitements()->count(),
                    'traitements_valides' => $c->traitements()->valides()->count(),
                    'traitements_brouillons' => $c->traitements()->where('statut', 'brouillon')->count(),
                    'signatures_actives' => Signature::where('client_id', $c->id)->where('statut', 'signee')->count(),
                    'registres_kyc' => RegistreKyc::where('client_id', $c->id)->count(),
                    'analyses' => Analyse::where('mission_id', '!=', null)
                        ->whereIn('mission_id', $c->missions()->pluck('id'))->count(),
                    'plans_actions_actifs' => PlanAction::where('client_id', $c->id)
                        ->whereIn('statut', ['propose', 'accepte_client', 'en_cours'])->count(),
                ],
            ];
        });

        return response()->json($clients);
    }

    /**
     * Vue 360 d'un client : toutes les donnees consolidees.
     */
    public function show(Request $request, Client $client): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo('view-portefeuille'),
            403,
            'Permission view-portefeuille requise.'
        );

        // User sans permission de gerer tout le portefeuille : verifier qu'il a acces a ce client.
        if (! $user->hasPermissionTo('view-all-portefeuille')) {
            abort_unless(
                $user->clients()->where('clients.id', $client->id)->exists(),
                403,
                'Ce client n\'est pas dans votre portefeuille.'
            );
        }

        $missionIds = $client->missions()->pluck('id');

        return response()->json([
            'client' => $client->load('utilisateurs:id,nom,prenom,email'),

            'traitements' => Traitement::where('client_id', $client->id)
                ->with('saisiPar:id,nom,prenom', 'validePar:id,nom,prenom')
                ->orderByDesc('updated_at')
                ->take(20)
                ->get(),

            'signatures' => Signature::where('client_id', $client->id)
                ->with('charte:id,type,titre,version,hash_contenu', 'user:id,nom,prenom')
                ->orderByDesc('signee_le')
                ->take(20)
                ->get(),

            'registres_kyc' => RegistreKyc::where('client_id', $client->id)
                ->with('genereur:id,nom,prenom')
                ->orderByDesc('created_at')
                ->take(10)
                ->get(),

            'analyses' => Analyse::whereIn('mission_id', $missionIds)
                ->with('lanceur:id,nom,prenom')
                ->orderByDesc('created_at')
                ->take(10)
                ->get(),

            'plans_actions' => PlanAction::where('client_id', $client->id)
                ->with('proposeur:id,nom,prenom')
                ->withCount('items')
                ->orderByDesc('created_at')
                ->take(10)
                ->get(),

            'stats' => [
                'traitements_total' => Traitement::where('client_id', $client->id)->count(),
                'traitements_valides' => Traitement::where('client_id', $client->id)->valides()->count(),
                'signatures_actives' => Signature::where('client_id', $client->id)->where('statut', 'signee')->count(),
                'registres_kyc' => RegistreKyc::where('client_id', $client->id)->count(),
                'plans_actions_actifs' => PlanAction::where('client_id', $client->id)
                    ->whereIn('statut', ['propose', 'accepte_client', 'en_cours'])->count(),
                'plans_actions_clotures' => PlanAction::where('client_id', $client->id)->where('statut', 'cloture')->count(),
            ],
        ]);
    }
}
