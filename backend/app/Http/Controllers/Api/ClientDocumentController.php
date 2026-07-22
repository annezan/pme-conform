<?php

/**
 * Controleur ClientDocumentController — Espace documents du client final.
 *
 * Reserve au role "client". Permet au client :
 *   - de lister ses propres documents (toutes missions confondues)
 *   - d'uploader un nouveau document (rattache automatiquement a une mission "Boite de reception")
 *   - de previsualiser le texte extrait
 *   - de supprimer un document
 *
 * Securite : un client ne peut voir/modifier que les documents lies aux missions
 * du Client (entreprise) auquel son compte est rattache via client_user.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDocumentJob;
use App\Models\Client;
use App\Models\Document;
use App\Models\Mission;
use App\Services\Audit\AuditService;
use App\Services\Document\ExtractorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(
    path: "/api/client-documents/initialiser",
    post: new OA\Post(
        operationId: "client-documents-initialiser",
        summary: "Initialiser l'espace client",
        description: "Crée un client (entreprise) et rattache l'utilisateur connecté",
        tags: ["Documents Client"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["raison_sociale"],
                properties: [
                    new OA\Property(property: "raison_sociale", type: "string", example: "Entreprise SAS"),
                    new OA\Property(property: "siret", type: "string", nullable: true, example: "12345678901234"),
                    new OA\Property(property: "adresse", type: "string", nullable: true),
                    new OA\Property(property: "code_postal", type: "string", nullable: true),
                    new OA\Property(property: "ville", type: "string", nullable: true),
                    new OA\Property(property: "pays", type: "string", nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Espace client initialisé avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Espace client initialisé."),
                        new OA\Property(property: "client", type: "object", properties: [
                            new OA\Property(property: "id", type: "integer"),
                            new OA\Property(property: "raison_sociale", type: "string"),
                            new OA\Property(property: "siret", type: "string", nullable: true)
                        ])
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/client-documents",
    get: new OA\Get(
        operationId: "client-documents-index",
        summary: "Lister les documents du client",
        description: "Retourne la liste des documents du client connecté (toutes missions confondues)",
        tags: ["Documents Client"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "search",
                in: "query",
                description: "Recherche dans le titre",
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
                description: "Liste des documents du client",
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
                                    new OA\Property(property: "titre", type: "string"),
                                    new OA\Property(property: "fichier_path", type: "string"),
                                    new OA\Property(property: "taille_octets", type: "integer"),
                                    new OA\Property(property: "mime_type", type: "string"),
                                    new OA\Property(property: "mission", type: "object", properties: [
                                        new OA\Property(property: "id", type: "integer"),
                                        new OA\Property(property: "reference", type: "string"),
                                        new OA\Property(property: "titre", type: "string")
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

class ClientDocumentController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private ExtractorFactory $extractorFactory,
    ) {}

    /**
     * Initialise l'espace du client connecte : cree un Client (entreprise) et l'y rattache.
     * Utilise lorsqu'un utilisateur avec role "client" n'est pas encore lie a une entreprise.
     */
    public function initialiser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'raison_sociale' => 'required|string|max:255',
            'sigle' => 'nullable|string|max:50',
            'secteur_activite' => 'nullable|string|max:255',
            'ville' => 'nullable|string|max:100',
            'pays' => 'nullable|string|max:100',
            'telephone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
        ]);

        $user = $request->user();

        if ($user->clients()->exists()) {
            return response()->json(['message' => 'Votre espace est deja initialise.'], 422);
        }

        $client = Client::create(array_merge($data, [
            'statut' => 'actif',
            'contact_principal_nom' => trim(($user->prenom ?? '') . ' ' . ($user->nom ?? '')),
            'contact_principal_email' => $user->email,
            'contact_principal_telephone' => $user->telephone,
            'contact_principal_poste' => $user->poste,
        ]));

        $user->clients()->attach($client->id, ['role_projet' => 'titulaire']);

        return response()->json([
            'message' => 'Espace client initialise avec succes.',
            'client' => $client,
        ], 201);
    }

    /**
     * Liste les documents accessibles au client connecte.
     *
     * Le filtrage se fait directement sur Document.client_id : pas besoin
     * de transiter par la mission. Cela permet aux documents de survivre a
     * la suppression d'une mission, et evite les artefacts type "Boite de
     * reception" dans la liste /missions.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Les ASC (view-portefeuille) voient les documents de TOUS les clients,
        // groupes par entreprise. Les autres (client/client_admin) ne voient que
        // ceux de leur propre entreprise.
        $estAsc = $user->hasPermissionTo('view-portefeuille');

        $query = Document::query()
            ->with([
                'client:id,raison_sociale',
                'mission' => fn ($q) => $q->withTrashed()->select('id', 'reference', 'titre', 'client_id', 'deleted_at'),
            ])
            ->withCount('chunks')
            ->where('type', 'document_client');

        if (! $estAsc) {
            $clientIds = $user->clients()->pluck('clients.id');
            if ($clientIds->isEmpty()) {
                return response()->json([
                    'data' => [],
                    'message' => 'Aucun client rattache a votre compte. Contactez votre administrateur.',
                ]);
            }
            $query->whereIn('client_id', $clientIds);
        }

        return response()->json(['data' => $query->latest()->get()]);
    }

    /**
     * Upload d'un document par le client. Le fichier est rattache directement
     * au Client (entreprise) de l'utilisateur connecte — aucune mission auto-creee.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fichier' => 'required|file|mimes:pdf,doc,docx,png,jpg,jpeg,webp,tiff,bmp,gif|max:20480',
            'titre' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'client_id' => 'nullable|exists:clients,id',
        ]);

        $user = $request->user();
        $estAsc = $user->hasPermissionTo('view-portefeuille');

        if ($estAsc) {
            // Cote ASC : le client cible doit etre fourni explicitement
            // (l'ASC n'a pas d'entreprise rattachee a son compte).
            if (empty($data['client_id'])) {
                return response()->json([
                    'message' => 'Vous devez préciser le client cible pour cet upload.',
                ], 422);
            }
            $clientId = (int) $data['client_id'];
        } else {
            // Cote client : on prend l'entreprise rattachee a son compte.
            $clientId = $user->clients()->value('clients.id');
            if (! $clientId) {
                return response()->json(['message' => 'Aucun client rattache a votre compte.'], 422);
            }
            // Garde-fou : si un client_id different est passe par erreur,
            // on l'ignore et on force l'entreprise rattachee.
        }

        $fichier = $request->file('fichier');
        $document = Document::create([
            'client_id' => $clientId,
            'mission_id' => null,
            'uploaded_by' => $user->id,
            'titre' => $data['titre'] ?? $fichier->getClientOriginalName(),
            'description' => $data['description'] ?? null,
            'nom_fichier_original' => $fichier->getClientOriginalName(),
            'type_mime' => $fichier->getMimeType(),
            'taille_octets' => $fichier->getSize(),
            'type' => 'document_client',
            'statut' => 'en_attente',
            'is_confidentiel' => true,
            'hash_fichier' => hash_file('sha256', $fichier->path()),
        ]);

        $document->addMedia($fichier)->toMediaCollection('fichiers');

        // Extraction immediate du texte si possible
        try {
            $media = $document->getFirstMedia('fichiers');
            if ($media && $this->extractorFactory->supporte($document->type_mime)) {
                $contenu = $this->extractorFactory->extraire($media->getPath(), $document->type_mime);
                $document->update(['contenu_extrait' => $contenu]);
            }
        } catch (\Throwable $e) {
            // Sera retente par le job
        }

        ProcessDocumentJob::dispatchAfterResponse($document);
        $this->audit->uploadDocument($document);

        return response()->json([
            'document' => $document->load('mission:id,reference,titre'),
            'message' => 'Document uploade avec succes. Indexation en cours.',
        ], 201);
    }

    /**
     * Detail + preview du contenu extrait.
     */
    public function show(Request $request, Document $document): JsonResponse
    {
        $this->verifierAcces($request->user(), $document);

        return response()->json([
            'document' => $document->load([
                'client:id,raison_sociale',
                'mission' => fn ($q) => $q->withTrashed()->select('id', 'reference', 'titre', 'client_id', 'deleted_at'),
            ])->loadCount('chunks'),
            'contenu_apercu' => mb_substr($document->contenu_extrait ?? '', 0, 5000),
        ]);
    }

    /**
     * Suppression (soft delete).
     */
    public function destroy(Request $request, Document $document): JsonResponse
    {
        $this->verifierAcces($request->user(), $document);
        $document->delete();

        return response()->json(['message' => 'Document supprime.']);
    }

    /**
     * Verifie que le document appartient au meme Client que l'utilisateur connecte.
     * Le lien est direct via documents.client_id ; un fallback sur la mission est
     * conserve pour les anciens documents qui n'auraient pas encore de client_id.
     */
    private function verifierAcces($user, Document $document): void
    {
        // Les ASC (view-portefeuille) accedent aux documents de tous les
        // clients, comme dans index(). Sans ce court-circuit, un ASC (qui n'a
        // aucun client rattache) recevait un 403 en ouvrant un document.
        if ($user->hasPermissionTo('view-portefeuille')) {
            return;
        }

        $clientIds = $user->clients()->pluck('clients.id');
        $clientDoc = $document->client_id;
        if (! $clientDoc && $document->mission_id) {
            $clientDoc = Mission::withTrashed()->find($document->mission_id)?->client_id;
        }

        if (! $clientDoc || ! $clientIds->contains($clientDoc)) {
            abort(403, 'Acces refuse : ce document n\'appartient pas a votre espace.');
        }
    }

}
