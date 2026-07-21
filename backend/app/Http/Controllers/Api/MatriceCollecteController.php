<?php

/**
 * MatriceCollecteController — API matrice de collecte (Methode 2 etape 1).
 *
 *   GET    /missions/{mission}/matrice          : etat actuel + pieces
 *   POST   /missions/{mission}/matrice/initier  : cree + envoie email au client (consultant ASC)
 *   PUT    /missions/{mission}/matrice          : enregistre les reponses du client
 *   POST   /missions/{mission}/matrice/pieces   : upload d'une piece de conviction
 *   DELETE /matrice-pieces/{piece}              : supprime une piece
 *   POST   /missions/{mission}/matrice/remettre : marque comme remise par le client
 *   POST   /missions/{mission}/matrice/valider  : validation par le consultant ASC
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDocumentJob;
use App\Mail\MatriceCollecteEnvoyee;
use App\Models\Document;
use App\Models\MatriceCollecte;
use App\Models\MatriceCollectePiece;
use App\Models\Mission;
use App\Models\Organigramme;
use App\Services\Document\ExtractorFactory;
use App\Services\Methode2\MatriceTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

#[OA\PathItem(
    path: "/api/missions/{mission_id}/matrice",
    get: new OA\Get(
        operationId: "matrice-show",
        summary: "Afficher la matrice de collecte",
        description: "Retourne l'état actuel de la matrice de collecte avec les réponses et pièces",
        tags: ["Matrice de Collecte"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "mission_id",
                in: "path",
                description: "ID de la mission",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Matrice de collecte",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "matrice", type: "object", properties: [
                            new OA\Property(property: "id", type: "integer"),
                            new OA\Property(property: "mission_id", type: "integer"),
                            new OA\Property(property: "reponses", type: "object", example: "pole1: { reponse: 'Données clients' }"),
                            new OA\Property(property: "statut", type: "string", enum: ["a_remplir", "en_cours", "remis", "valide"]),
                            new OA\Property(property: "pieces", type: "array", items: new OA\Items(type: "object")),
                            new OA\Property(property: "created_at", type: "string", format: "date-time"),
                            new OA\Property(property: "updated_at", type: "string", format: "date-time")
                        ])
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Accès non autorisé"),
            new OA\Response(response: 404, description: "Mission non trouvée")
        ]
    ),
    put: new OA\Put(
        operationId: "matrice-update",
        summary: "Mettre à jour la matrice",
        description: "Enregistre les réponses du client dans la matrice de collecte",
        tags: ["Matrice de Collecte"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "mission_id",
                in: "path",
                description: "ID de la mission",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["reponses"],
                properties: [
                    new OA\Property(property: "reponses", type: "object", example: "pole1: { reponse: 'Données clients et employés' }, pole2: { reponse: 'Processus marketing' }")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Matrice mise à jour avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Matrice mise à jour."),
                        new OA\Property(property: "matrice", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Accès non autorisé"),
            new OA\Response(response: 404, description: "Mission non trouvée"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/missions/{mission_id}/matrice/initier",
    post: new OA\Post(
        operationId: "matrice-initier",
        summary: "Initier la matrice",
        description: "Crée la matrice et envoie un email au client pour la remplir",
        tags: ["Matrice de Collecte"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "mission_id",
                in: "path",
                description: "ID de la mission",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: "Matrice initiée avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Matrice initiée et email envoyé."),
                        new OA\Property(property: "matrice", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Accès non autorisé"),
            new OA\Response(response: 404, description: "Mission non trouvée")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/missions/{mission_id}/matrice/remettre",
    post: new OA\Post(
        operationId: "matrice-remettre",
        summary: "Remettre la matrice",
        description: "Marque la matrice comme remise par le client",
        tags: ["Matrice de Collecte"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "mission_id",
                in: "path",
                description: "ID de la mission",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Matrice remise avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Matrice remise.")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Accès non autorisé"),
            new OA\Response(response: 404, description: "Mission non trouvée")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/missions/{mission_id}/matrice/valider",
    post: new OA\Post(
        operationId: "matrice-valider",
        summary: "Valider la matrice",
        description: "Valide la matrice par le consultant ASC",
        tags: ["Matrice de Collecte"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "mission_id",
                in: "path",
                description: "ID de la mission",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Matrice validée avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Matrice validée.")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Accès non autorisé"),
            new OA\Response(response: 404, description: "Mission non trouvée")
        ]
    )
)]

class MatriceCollecteController extends Controller
{
    public function __construct(
        private ExtractorFactory $extractorFactory,
    ) {}

    public function show(Request $request, Mission $mission): JsonResponse
    {
        $this->verifierAcces($request->user(), $mission);

        $matrice = MatriceCollecte::firstOrCreate(['mission_id' => $mission->id]);
        // Pre-remplit avec le template structure si la matrice est vide
        if (empty($matrice->reponses)) {
            $matrice->update(['reponses' => MatriceTemplate::defaut()]);
        }
        $matrice->load(['pieces.uploadeur:id,nom,prenom', 'validatrice:id,nom,prenom']);

        return response()->json(['matrice' => $matrice]);
    }

    /**
     * Liste les matrices accessibles au client connecte (toutes ses missions).
     */
    public function indexClient(Request $request): JsonResponse
    {
        $user = $request->user();
        $clientIds = $user->clients()->pluck('clients.id');

        if ($clientIds->isEmpty()) {
            return response()->json(['data' => []]);
        }

        // Pour chaque mission methode_2 du client, retourner la matrice (en
        // creant celle qui manque). Les missions methode_1 n'ont pas de matrice.
        $missions = Mission::whereIn('client_id', $clientIds)
            ->where('methode', 'methode_2')
            ->with('client:id,raison_sociale')
            ->orderByDesc('created_at')
            ->get();

        $data = [];
        foreach ($missions as $mission) {
            $matrice = MatriceCollecte::firstOrCreate(['mission_id' => $mission->id]);
            if (empty($matrice->reponses)) {
                $matrice->update(['reponses' => MatriceTemplate::defaut()]);
            }
            $data[] = [
                'matrice' => $matrice->fresh(['pieces']),
                'mission' => [
                    'id' => $mission->id,
                    'reference' => $mission->reference,
                    'titre' => $mission->titre,
                    'methode' => $mission->methode,
                    'client' => $mission->client ? [
                        'id' => $mission->client->id,
                        'raison_sociale' => $mission->client->raison_sociale,
                    ] : null,
                ],
            ];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Derive la structure d'organigramme depuis les reponses de la matrice
     * et l'enregistre comme structure de l'organigramme de la mission (en
     * statut 'en_cours' — l'agent ASC peut ensuite ajuster et figer).
     */
    public function deriverOrganigramme(Request $request, Mission $mission): JsonResponse
    {
        $this->verifierAcces($request->user(), $mission);

        $matrice = MatriceCollecte::where('mission_id', $mission->id)->firstOrFail();
        $structure = MatriceTemplate::deriverOrganigramme($matrice->reponses ?? []);

        if (empty($structure)) {
            return response()->json([
                'message' => 'Aucun pole renseigne dans la matrice (services/postes vides). Completez d\'abord la matrice.',
            ], 422);
        }

        $organigramme = Organigramme::firstOrCreate(['mission_id' => $mission->id]);
        $organigramme->update([
            'mode' => 'formulaire',
            'structure' => $structure,
            'statut' => 'en_cours',
        ]);

        return response()->json([
            'message' => count($structure) . ' pole(s) deduit(s) et inseres dans l\'organigramme.',
            'organigramme' => $organigramme->fresh(),
            'redirect_to' => "/missions/{$mission->id}/organigramme",
        ]);
    }

    private function verifierAcces($user, Mission $mission): void
    {
        // Si l'utilisateur a la permission de voir/gerer toutes les matrices, pas de scope.
        if (! $user || $user->hasAnyPermission(['view-matrice', 'view-all-matrice'])) {
            return;
        }
        $clientIds = $user->clients()->pluck('clients.id');
        if (! $clientIds->contains($mission->client_id)) {
            abort(403, 'Acces refuse : cette mission ne fait pas partie de votre espace.');
        }
    }

    public function initier(Request $request, Mission $mission): JsonResponse
    {
        if ($mission->methode !== 'methode_2') {
            return response()->json(['message' => 'Cette operation est reservee aux missions Methode 2.'], 422);
        }

        $matrice = MatriceCollecte::firstOrCreate(['mission_id' => $mission->id]);
        $matrice->update(['statut' => 'a_remplir', 'envoyee_a' => now()]);

        // Envoi email au contact principal du client (si dispo)
        $emailContact = $mission->client->contact_principal_email ?: $mission->client->email;
        if ($emailContact) {
            try {
                Mail::to($emailContact)->send(new MatriceCollecteEnvoyee($mission));
            } catch (\Throwable $e) {
                return response()->json([
                    'matrice' => $matrice,
                    'message' => "Matrice initialisee. Email non envoye : {$e->getMessage()}",
                ]);
            }
        }

        return response()->json([
            'matrice' => $matrice,
            'message' => $emailContact
                ? 'Matrice envoyee par email au client (' . $emailContact . ').'
                : 'Matrice initialisee. Aucun email contact configure.',
        ]);
    }

    public function update(Request $request, Mission $mission): JsonResponse
    {
        $this->verifierAcces($request->user(), $mission);

        $data = $request->validate([
            'reponses' => 'nullable|array',
            'reponses_libres' => 'nullable|string',
        ]);

        $matrice = MatriceCollecte::firstOrCreate(['mission_id' => $mission->id]);
        $patch = $data;
        if ($matrice->statut === 'a_remplir') {
            $patch['statut'] = 'en_cours';
        }
        $matrice->update($patch);

        return response()->json(['matrice' => $matrice->fresh()]);
    }

    public function remettre(Request $request, Mission $mission): JsonResponse
    {
        $this->verifierAcces($request->user(), $mission);

        $matrice = MatriceCollecte::where('mission_id', $mission->id)->firstOrFail();
        $matrice->update(['statut' => 'remise', 'remise_a' => now()]);

        return response()->json(['matrice' => $matrice, 'message' => 'Matrice remise. AS Consulting va l\'analyser.']);
    }

    public function valider(Request $request, Mission $mission): JsonResponse
    {
        $matrice = MatriceCollecte::where('mission_id', $mission->id)->firstOrFail();
        $matrice->update([
            'statut' => 'validee',
            'validee_par' => $request->user()->id,
            'validee_at' => now(),
        ]);

        return response()->json(['matrice' => $matrice->fresh(), 'message' => 'Matrice validee.']);
    }

    public function uploaderPiece(Request $request, Mission $mission): JsonResponse
    {
        $this->verifierAcces($request->user(), $mission);

        $request->validate([
            'fichier' => 'required|file|max:25600', // 25 Mo
            'libelle' => 'required|string|max:255',
            'pole_code' => 'nullable|string|max:100',
            'item_code' => 'nullable|string|max:100',
        ]);
        $matrice = MatriceCollecte::firstOrCreate(['mission_id' => $mission->id]);
        $fichier = $request->file('fichier');

        // On stocke physiquement le fichier sous matrices/{matrice_id} pour
        // garder la traceabilite cote matrice. La meme piece est aussi exposee
        // comme Document de l'espace client (visible dans /mes-documents) via
        // Spatie Media Library, ce qui permet la previsualisation + extraction.
        $cheminLocal = $fichier->store("matrices/{$matrice->id}", 'local');
        $cheminAbsolu = Storage::disk('local')->path($cheminLocal);

        $piece = DB::transaction(function () use ($request, $matrice, $mission, $fichier, $cheminLocal, $cheminAbsolu) {
            $document = Document::create([
                'mission_id' => $mission->id,
                'client_id' => $mission->client_id,
                'uploaded_by' => $request->user()->id,
                'titre' => $request->libelle,
                'description' => 'Piece justificative matrice — pole : ' . ($request->input('pole_code') ?? 'n/a')
                    . ' / item : ' . ($request->input('item_code') ?? 'n/a'),
                'nom_fichier_original' => $fichier->getClientOriginalName(),
                'type_mime' => $fichier->getMimeType(),
                'taille_octets' => $fichier->getSize(),
                'type' => 'document_client',
                'statut' => 'en_attente',
                'is_confidentiel' => true,
                'hash_fichier' => hash_file('sha256', $cheminAbsolu),
                'metadata' => [
                    'source' => 'matrice_collecte',
                    'matrice_id' => $matrice->id,
                    'pole_code' => $request->input('pole_code'),
                    'item_code' => $request->input('item_code'),
                ],
            ]);

            // Attache le fichier au Document via Spatie Media Library pour que
            // /mes-documents le previsualise et l'expose normalement. On utilise
            // ->preservingOriginal() pour ne pas supprimer le fichier qu'on a
            // deja stocke dans matrices/{id} (la piece pointe encore dessus).
            try {
                $document->addMedia($cheminAbsolu)
                    ->preservingOriginal()
                    ->toMediaCollection('fichiers');

                // Extraction immediate du contenu (best-effort)
                if ($this->extractorFactory->supporte($document->type_mime)) {
                    $contenu = $this->extractorFactory->extraire($cheminAbsolu, $document->type_mime);
                    $document->update(['contenu_extrait' => $contenu]);
                }
            } catch (\Throwable $e) {
                Log::warning("MatriceCollecte: extraction/media echouee pour Document {$document->id}", [
                    'error' => $e->getMessage(),
                ]);
            }

            $newPiece = MatriceCollectePiece::create([
                'matrice_collecte_id' => $matrice->id,
                'document_id' => $document->id,
                'uploade_par' => $request->user()->id,
                'pole_code' => $request->input('pole_code'),
                'item_code' => $request->input('item_code'),
                'libelle' => $request->libelle,
                'chemin' => $cheminLocal,
                'mime' => $fichier->getMimeType(),
                'taille_octets' => $fichier->getSize(),
            ]);

            // Indexation asynchrone : meme pipeline que les uploads via /mes-documents
            // (extraction approfondie, chunks, embeddings -> statut "indexe").
            ProcessDocumentJob::dispatchAfterResponse($document);

            return $newPiece;
        });

        return response()->json(['piece' => $piece->load('document:id,titre,statut')], 201);
    }

    public function supprimerPiece(MatriceCollectePiece $piece): JsonResponse
    {
        DB::transaction(function () use ($piece) {
            // Le fichier physique local
            if ($piece->chemin && Storage::disk('local')->exists($piece->chemin)) {
                Storage::disk('local')->delete($piece->chemin);
            }
            // Le Document lie (avec ses medias Spatie en cascade) — supprime
            // aussi son entree dans /mes-documents.
            if ($piece->document_id) {
                Document::where('id', $piece->document_id)->each(function (Document $doc) {
                    $doc->clearMediaCollection('fichiers');
                    $doc->delete();
                });
            }
            $piece->delete();
        });

        return response()->json(['message' => 'Piece supprimee.']);
    }

    /**
     * Ajoute dynamiquement un nouveau pole a la matrice de collecte.
     *
     * Le pole vient se greffer a la fin du tableau `reponses` avec un code
     * unique genere automatiquement (pole_custom_<n>). Le client (ou le
     * consultant) peut ensuite y ajouter des items via la mise a jour
     * standard de la matrice (PUT /missions/{mission}/matrice).
     */
    public function ajouterPole(Request $request, Mission $mission): JsonResponse
    {
        $this->verifierAcces($request->user(), $mission);

        $data = $request->validate([
            'pole' => 'required|string|max:255',
            'cibles' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.libelle' => 'required_with:items|string|max:255',
            'items.*.attendu' => 'nullable|string',
        ]);

        $matrice = MatriceCollecte::firstOrCreate(['mission_id' => $mission->id]);
        $reponses = $matrice->reponses ?: \App\Services\Methode2\MatriceTemplate::defaut();

        // Calcul d'un code unique pour le nouveau pole.
        $codesExistants = array_column($reponses, 'code');
        $i = 1;
        do {
            $nouveauCode = sprintf('pole_custom_%d', $i++);
        } while (in_array($nouveauCode, $codesExistants, true));

        $items = [];
        foreach (($data['items'] ?? []) as $index => $item) {
            $items[] = [
                'code' => sprintf('item_%d', $index + 1),
                'libelle' => $item['libelle'],
                'attendu' => $item['attendu'] ?? '',
                'reponse' => '',
            ];
        }

        $nouveauPole = [
            'code' => $nouveauCode,
            'pole' => $data['pole'],
            'cibles' => $data['cibles'] ?? '',
            'description' => $data['description'] ?? '',
            'items' => $items,
            'organigramme' => [
                ['code' => 'services', 'libelle' => 'Services (separes par virgule)', 'type' => 'liste', 'reponse' => ''],
                ['code' => 'postes', 'libelle' => 'Postes cles (separes par virgule)', 'type' => 'liste', 'reponse' => ''],
            ],
        ];

        $reponses[] = $nouveauPole;
        $matrice->update(['reponses' => $reponses]);

        return response()->json([
            'message' => 'Pole "' . $data['pole'] . '" ajoute a la matrice.',
            'pole' => $nouveauPole,
            'matrice' => $matrice->fresh(['pieces']),
        ], 201);
    }

    /**
     * Enregistre la reponse textuelle d'un item precis de la matrice.
     *
     * Cible un pole (pole_code) et un item (item_code) dans le JSON
     * reponses ; permet une mise a jour granulaire sans renvoyer toute la
     * structure depuis le front.
     */
    public function repondreItem(Request $request, Mission $mission): JsonResponse
    {
        $this->verifierAcces($request->user(), $mission);

        $data = $request->validate([
            'pole_code' => 'required|string|max:100',
            'item_code' => 'required|string|max:100',
            'reponse' => 'nullable|string',
        ]);

        $matrice = MatriceCollecte::firstOrCreate(['mission_id' => $mission->id]);
        $reponses = $matrice->reponses ?: [];
        $modifie = false;

        foreach ($reponses as &$pole) {
            if (($pole['code'] ?? null) !== $data['pole_code']) {
                continue;
            }
            foreach (($pole['items'] ?? []) as &$item) {
                if (($item['code'] ?? null) === $data['item_code']) {
                    $item['reponse'] = (string) ($data['reponse'] ?? '');
                    $modifie = true;
                    break 2;
                }
            }
        }
        unset($pole, $item);

        if (! $modifie) {
            return response()->json([
                'message' => 'Item introuvable pour ce pole.',
            ], 404);
        }

        $patch = ['reponses' => $reponses];
        if ($matrice->statut === 'a_remplir') {
            $patch['statut'] = 'en_cours';
        }
        $matrice->update($patch);

        return response()->json(['matrice' => $matrice->fresh(['pieces'])]);
    }
}
