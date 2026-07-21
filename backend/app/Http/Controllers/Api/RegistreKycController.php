<?php

/**
 * Controleur RegistreKycController — Generation & consultation des registres
 * des traitements (fichiers .docx horodates et empreintes).
 *
 * - client/client_admin : genere et consulte les registres de son entreprise
 * - admin/manager/consultant : consulte tous les registres (de ses clients)
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\RegistreKyc;
use App\Services\Audit\AuditService;
use App\Services\Registre\RegistreKycGenerator;
use App\Services\Registre\RegistreMobisoftExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[OA\PathItem(
    path: "/api/registres-kyc",
    get: new OA\Get(
        operationId: "registres-kyc-index",
        summary: "Lister les registres KYC",
        description: "Retourne la liste des registres de traitements générés avec filtres par rôle",
        tags: ["Registres KYC"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "client_id",
                in: "query",
                description: "Filtrer par client",
                required: false,
                schema: new OA\Schema(type: "integer")
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
                description: "Liste des registres KYC",
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
                                    new OA\Property(property: "client_id", type: "integer"),
                                    new OA\Property(property: "genere_par", type: "integer"),
                                    new OA\Property(property: "fichier_path", type: "string"),
                                    new OA\Property(property: "empreinte", type: "string"),
                                    new OA\Property(property: "genere_le", type: "string", format: "date-time"),
                                    new OA\Property(property: "client", type: "object", properties: [
                                        new OA\Property(property: "id", type: "integer"),
                                        new OA\Property(property: "raison_sociale", type: "string")
                                    ]),
                                    new OA\Property(property: "genereur", type: "object", properties: [
                                        new OA\Property(property: "id", type: "integer"),
                                        new OA\Property(property: "nom", type: "string"),
                                        new OA\Property(property: "prenom", type: "string")
                                    ])
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié")
        ]
    )
)]

class RegistreKycController extends Controller
{
    public function __construct(
        private RegistreKycGenerator $generator,
        private RegistreMobisoftExporter $mobisoftExporter,
        private AuditService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = RegistreKyc::query()
            ->with(['client:id,raison_sociale', 'genereur:id,nom,prenom']);

        // User sans permission de gerer tous les registres : scoper a ses propres clients.
        if (! $user->hasPermissionTo('view-all-registres-kyc')) {
            $clientIds = $user->clients()->pluck('clients.id');
            $query->whereIn('client_id', $clientIds);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermissionTo('create-registres-kyc'),
            403,
            'Vous n\'avez pas la permission de generer un registre.'
        );

        $user = $request->user();
        $clientId = null;

        // User sans permission de generation globale : genere pour SA propre entreprise
        // (typiquement role client/client_admin). Sinon : peut choisir le client cible.
        if (! $user->hasPermissionTo('view-all-registres-kyc')) {
            $clientId = $user->clients()->value('clients.id');
            abort_unless($clientId, 422, 'Aucune entreprise rattachee a votre compte.');
            $format = $request->input('format', 'xlsx');
        } else {
            $data = $request->validate([
                'client_id' => 'required|exists:clients,id',
                'format' => 'nullable|in:xlsx,docx',
            ]);
            $clientId = $data['client_id'];
            $format = $data['format'] ?? 'xlsx';
        }

        $client = Client::findOrFail($clientId);

        $nbValides = $client->traitements()->valides()->count();
        if ($nbValides === 0) {
            return response()->json([
                'message' => 'Aucun traitement valide pour generer un registre. Validez au moins une fiche de traitement avant.',
            ], 422);
        }

        try {
            $registre = $format === 'docx'
                ? $this->generator->generer($client, $user)
                : $this->mobisoftExporter->generer($client, $user);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Erreur lors de la generation : ' . $e->getMessage(),
            ], 500);
        }

        $this->audit->log('registre_kyc.genere', [
            'registre_id' => $registre->id,
            'client_id' => $clientId,
            'nb_traitements' => $registre->nb_traitements,
        ]);

        return response()->json([
            'registre' => $registre->load('client:id,raison_sociale', 'genereur:id,nom,prenom'),
            'message' => 'Registre genere avec succes.',
        ], 201);
    }

    public function show(Request $request, RegistreKyc $registre): JsonResponse
    {
        $this->verifierAcces($request->user(), $registre);

        $registre->load(['client:id,raison_sociale', 'client.secteursActivite:id,nom', 'genereur:id,nom,prenom']);

        return response()->json([
            'registre' => $registre,
            'fichier_existe' => ! empty($registre->fichier_path)
                && Storage::disk('local')->exists($registre->fichier_path),
        ]);
    }

    public function telecharger(Request $request, RegistreKyc $registre): BinaryFileResponse
    {
        $this->verifierAcces($request->user(), $registre);

        abort_unless(
            $registre->fichier_path && Storage::disk('local')->exists($registre->fichier_path),
            404,
            'Fichier non disponible.'
        );

        $cheminAbsolu = Storage::disk('local')->path($registre->fichier_path);
        $extension = $registre->format ?: 'docx';
        $nomFichier = 'registre-' . $registre->reference . '.' . $extension;
        $contentType = $extension === 'xlsx'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        $this->audit->log('registre_kyc.telecharge', ['registre_id' => $registre->id]);

        return response()->download($cheminAbsolu, $nomFichier, [
            'Content-Type' => $contentType,
        ]);
    }

    public function destroy(Request $request, RegistreKyc $registre): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermissionTo('delete-registres-kyc'),
            403,
            'Permission delete-registres-kyc requise.'
        );
        $this->verifierAcces($request->user(), $registre);

        if ($registre->fichier_path && Storage::disk('local')->exists($registre->fichier_path)) {
            Storage::disk('local')->delete($registre->fichier_path);
        }
        $registre->delete();

        return response()->json(['message' => 'Registre supprime.']);
    }

    private function verifierAcces($user, RegistreKyc $registre): void
    {
        // Permission de gestion globale : pas de scope.
        if ($user->hasPermissionTo('view-all-registres-kyc')) {
            return;
        }

        // Sinon, l'utilisateur doit avoir le registre dans son perimetre client.
        if ($user->hasPermissionTo('view-registres-kyc')) {
            $clientIds = $user->clients()->pluck('clients.id');
            if (! $clientIds->contains($registre->client_id)) {
                abort(403, 'Ce registre ne concerne pas votre entreprise.');
            }

            return;
        }

        abort(403);
    }
}
