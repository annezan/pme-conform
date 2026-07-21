<?php

/**
 * Controleur EcartController — Consultation et suivi des ecarts.
 *
 * Les ecarts sont crees par le GapAnalysisService.
 * Ce controleur sert a les lister, mettre a jour le statut de correction,
 * assigner un consultant et ajouter des notes.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ecart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(
    path: "/api/ecarts",
    get: new OA\Get(
        operationId: "ecarts-index",
        summary: "Lister les écarts",
        description: "Retourne la liste paginée des écarts de conformité avec filtres avancés",
        tags: ["Écarts"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "analyse_id",
                in: "query",
                description: "Filtrer par analyse",
                required: false,
                schema: new OA\Schema(type: "integer")
            ),
            new OA\Parameter(
                name: "gravite",
                in: "query",
                description: "Filtrer par gravité (critique, majeur, mineur, observation)",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["critique", "majeur", "mineur", "observation"])
            ),
            new OA\Parameter(
                name: "categorie",
                in: "query",
                description: "Filtrer par catégorie",
                required: false,
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "statut_correction",
                in: "query",
                description: "Filtrer par statut de correction",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["ouvert", "en_cours", "traite", "accepte_par_client", "rejete"])
            ),
            new OA\Parameter(
                name: "assigne_a",
                in: "query",
                description: "Filtrer par assigné",
                required: false,
                schema: new OA\Schema(type: "integer")
            ),
            new OA\Parameter(
                name: "mission_id",
                in: "query",
                description: "Filtrer par mission",
                required: false,
                schema: new OA\Schema(type: "integer")
            ),
            new OA\Parameter(
                name: "per_page",
                in: "query",
                description: "Nombre d'éléments par page",
                required: false,
                schema: new OA\Schema(type: "integer", default: 25)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des écarts",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/Ecart")
                        ),
                        new OA\Property(
                            property: "links",
                            type: "object",
                            properties: [
                                new OA\Property(property: "first", type: "string"),
                                new OA\Property(property: "last", type: "string"),
                                new OA\Property(property: "prev", type: "string", nullable: true),
                                new OA\Property(property: "next", type: "string", nullable: true)
                            ]
                        ),
                        new OA\Property(
                            property: "meta",
                            type: "object",
                            properties: [
                                new OA\Property(property: "current_page", type: "integer"),
                                new OA\Property(property: "from", type: "integer"),
                                new OA\Property(property: "last_page", type: "integer"),
                                new OA\Property(property: "per_page", type: "integer"),
                                new OA\Property(property: "to", type: "integer"),
                                new OA\Property(property: "total", type: "integer")
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/ecarts/{id}",
    get: new OA\Get(
        operationId: "ecarts-show",
        summary: "Afficher un écart",
        description: "Retourne les détails complets d'un écart avec toutes ses relations",
        tags: ["Écarts"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID de l'écart",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Détails de l'écart",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "ecart", ref: "#/components/schemas/Ecart")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 404, description: "Écart non trouvé")
        ]
    ),
    put: new OA\Put(
        operationId: "ecarts-update",
        summary: "Mettre à jour un écart",
        description: "Met à jour le statut de correction, l'assignation et les notes d'un écart",
        tags: ["Écarts"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID de l'écart",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "gravite", type: "string", enum: ["critique", "majeur", "mineur", "observation"], nullable: true),
                    new OA\Property(property: "statut_correction", type: "string", enum: ["ouvert", "en_cours", "traite", "accepte_par_client", "rejete"], nullable: true),
                    new OA\Property(property: "assigne_a", type: "integer", nullable: true, example: 2),
                    new OA\Property(property: "echeance_correction", type: "string", format: "date", nullable: true, example: "2024-02-15"),
                    new OA\Property(property: "notes_consultant", type: "string", nullable: true, example: "Prioriser la mise en conformité"),
                    new OA\Property(property: "recommandation", type: "string", nullable: true, example: "Mettre en place un registre RGPD conforme")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Écart mis à jour avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "ecart", ref: "#/components/schemas/Ecart")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé (réservé aux non-clients)"),
            new OA\Response(response: 404, description: "Écart non trouvé"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    ),
    delete: new OA\Delete(
        operationId: "ecarts-destroy",
        summary: "Supprimer un écart",
        description: "Supprime un écart de manière irréversible",
        tags: ["Écarts"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID de l'écart",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Écart supprimé avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Écart supprimé.")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé (réservé aux non-clients)"),
            new OA\Response(response: 404, description: "Écart non trouvé")
        ]
    )
)]

class EcartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Ecart::query()
            ->with([
                'analyse:id,reference,mission_id',
                'analyse.mission:id,reference,titre,client_id',
                'analyse.mission.client:id,raison_sociale',
                'referentiel:id,code,titre,article_reference',
                'document:id,titre',
                'assigne:id,nom,prenom',
            ]);

        // User sans permission "voir tous les ecarts" : scoper aux entreprises rattachees.
        $user = $request->user();
        if (! $user->hasAnyPermission(['view-ecarts', 'view-all-ecarts'])) {
            $clientIds = $user->clients()->pluck('clients.id');
            $query->whereHas('analyse.mission', fn ($q) => $q->whereIn('client_id', $clientIds));
        }

        if ($request->filled('analyse_id')) {
            $query->where('analyse_id', $request->analyse_id);
        }
        if ($request->filled('gravite')) {
            $query->where('gravite', $request->gravite);
        }
        if ($request->filled('categorie')) {
            $query->where('categorie', $request->categorie);
        }
        if ($request->filled('statut_correction')) {
            $query->where('statut_correction', $request->statut_correction);
        }
        if ($request->filled('assigne_a')) {
            $query->where('assigne_a', $request->assigne_a);
        }
        if ($request->filled('mission_id')) {
            $query->whereHas('analyse', fn ($q) => $q->where('mission_id', $request->mission_id));
        }

        $query->orderByRaw("CASE gravite WHEN 'critique' THEN 1 WHEN 'majeur' THEN 2 WHEN 'mineur' THEN 3 ELSE 4 END");

        return response()->json($query->paginate(25));
    }

    public function show(Ecart $ecart): JsonResponse
    {
        $ecart->load([
            'analyse.mission.client',
            'referentiel',
            'referentielChunk',
            'document',
            'assigne:id,nom,prenom',
        ]);

        return response()->json(['ecart' => $ecart]);
    }

    public function update(Request $request, Ecart $ecart): JsonResponse
    {
        $user = $request->user();
        // Un user qui n'a pas la permission de gestion des ecarts est traite comme un client :
        // acces uniquement aux ecarts de ses propres entreprises, modifications limitees au statut.
        $aPermissionEcriture = $user->hasAnyPermission(['update-ecarts', 'view-all-ecarts']);

        if (! $aPermissionEcriture) {
            // Client : verifier que l'ecart appartient bien a une mission
            // d'une entreprise rattachee a l'utilisateur.
            $clientIds = $user->clients()->pluck('clients.id');
            $clientId = $ecart->analyse?->mission?->client_id;
            abort_unless($clientId && $clientIds->contains($clientId), 403, 'Acces refuse.');

            // Et il ne peut modifier que le statut, avec une whitelist excluant
            // « traite » qui reste une decision consultant.
            $data = $request->validate([
                'statut_correction' => 'sometimes|in:ouvert,en_cours,accepte_par_client,rejete',
            ]);
        } else {
            $data = $request->validate([
                'gravite' => 'sometimes|in:critique,majeur,mineur,observation',
                'statut_correction' => 'sometimes|in:ouvert,en_cours,traite,accepte_par_client,rejete',
                'assigne_a' => 'nullable|exists:users,id',
                'echeance_correction' => 'nullable|date',
                'notes_consultant' => 'nullable|string',
                'recommandation' => 'nullable|string',
                'risque' => 'nullable|string',
                'description_ecart' => 'nullable|string',
            ]);
        }

        $ecart->update($data);

        return response()->json(['ecart' => $ecart->fresh()]);
    }

    public function destroy(Request $request, Ecart $ecart): JsonResponse
    {
        if (! $request->user()->hasAnyPermission(['delete-ecarts', 'view-all-ecarts'])) {
            abort(403, 'Action non autorisee : permission delete-ecarts manquante.');
        }

        $ecart->delete();

        return response()->json(['message' => 'Ecart supprime.']);
    }
}
