<?php

/**
 * Controleur ReferentielController — CRUD des referentiels legaux.
 *
 * Les referentiels sont globaux a la plateforme.
 * Seuls les admins/managers peuvent les creer/modifier.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\IndexReferentielJob;
use App\Models\Referentiel;
use App\Services\Document\ExtractorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;


#[OA\PathItem(
    path: "/api/referentiels",
    get: new OA\Get(
        operationId: "referentiels-index",
        summary: "Lister tous les référentiels",
        description: "Retourne la liste paginée des référentiels légaux et réglementaires",
        tags: ["Référentiels"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "statut",
                in: "query",
                description: "Filtrer par statut (actif, inactif, archive)",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["actif", "inactif", "archive"])
            ),
            new OA\Parameter(
                name: "type",
                in: "query",
                description: "Filtrer par type (loi, decret, arrete, directive, norme, guide, autre)",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["loi", "decret", "arrete", "directive", "norme", "guide", "autre"])
            ),
            new OA\Parameter(
                name: "secteur",
                in: "query",
                description: "Filtrer par secteur d'activité",
                required: false,
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "q",
                in: "query",
                description: "Recherche dans titre, code, autorité",
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
                description: "Liste des référentiels",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/Referentiel")
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
    path: "/api/referentiels/{id}",
    get: new OA\Get(
        operationId: "referentiels-show",
        summary: "Afficher un référentiel",
        description: "Retourne les détails complets d'un référentiel",
        tags: ["Référentiels"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID du référentiel",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Détails du référentiel",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "referentiel", ref: "#/components/schemas/Referentiel")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 404, description: "Référentiel non trouvé")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/referentiels",
    post: new OA\Post(
        operationId: "referentiels-store",
        summary: "Créer un nouveau référentiel",
        description: "Crée un nouveau référentiel légal ou réglementaire avec optionnellement un fichier",
        tags: ["Référentiels"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                required: ["code", "titre", "type"],
                properties: [
                    new OA\Property(property: "code", type: "string", maxLength: 64, example: "RGPD-V2"),
                    new OA\Property(property: "titre", type: "string", maxLength: 255, example: "RGPD Rénové 2024"),
                    new OA\Property(property: "description", type: "string", nullable: true, example: "Nouvelle version du RGPD"),
                    new OA\Property(property: "autorite", type: "string", nullable: true, example: "ARTCI"),
                    new OA\Property(property: "version", type: "string", nullable: true, example: "2024"),
                    new OA\Property(property: "date_publication", type: "string", format: "date", nullable: true, example: "2024-01-15"),
                    new OA\Property(property: "date_entree_vigueur", type: "string", format: "date", nullable: true, example: "2024-01-15"),
                    new OA\Property(property: "type", type: "string", enum: ["loi", "decret", "arrete", "directive", "norme", "guide", "autre"], example: "loi"),
                    new OA\Property(property: "secteurs_activite_ids", type: "array", items: new OA\Items(type: "integer"), nullable: true, example: [1, 3, 7]),
                    new OA\Property(property: "source_url", type: "string", nullable: true, example: "https://example.com/loi.pdf"),
                    new OA\Property(property: "fichier", type: "string", format: "binary", nullable: true, description: "Fichier PDF/DOC/DOCX (max 50MB)")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Référentiel créé avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "referentiel", ref: "#/components/schemas/Referentiel")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/referentiels/{id}",
    put: new OA\Put(
        operationId: "referentiels-update",
        summary: "Mettre à jour un référentiel",
        description: "Met à jour les informations d'un référentiel existant",
        tags: ["Référentiels"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID du référentiel",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                properties: [
                    new OA\Property(property: "titre", type: "string", nullable: true, example: "Titre modifié"),
                    new OA\Property(property: "description", type: "string", nullable: true, example: "Description modifiée"),
                    new OA\Property(property: "autorite", type: "string", nullable: true, example: "ARTCI"),
                    new OA\Property(property: "version", type: "string", nullable: true, example: "2024"),
                    new OA\Property(property: "date_publication", type: "string", format: "date", nullable: true, example: "2024-01-15"),
                    new OA\Property(property: "date_entree_vigueur", type: "string", format: "date", nullable: true, example: "2024-01-15"),
                    new OA\Property(property: "type", type: "string", enum: ["loi", "decret", "arrete", "directive", "norme", "guide", "autre"], nullable: true, example: "loi"),
                    new OA\Property(property: "secteurs_activite_ids", type: "array", items: new OA\Items(type: "integer"), nullable: true, example: [1, 3, 7]),
                    new OA\Property(property: "statut", type: "string", enum: ["actif", "obsolete", "brouillon"], nullable: true, example: "actif"),
                    new OA\Property(property: "source_url", type: "string", nullable: true, example: "https://example.com/loi.pdf")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Référentiel mis à jour avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "referentiel", ref: "#/components/schemas/Referentiel")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé"),
            new OA\Response(response: 404, description: "Référentiel non trouvé"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/referentiels/{id}",
    delete: new OA\Delete(
        operationId: "referentiels-destroy",
        summary: "Supprimer un référentiel",
        description: "Supprime définitivement un référentiel et ses fichiers associés",
        tags: ["Référentiels"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID du référentiel",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Référentiel supprimé avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Référentiel supprimé avec succès")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé"),
            new OA\Response(response: 404, description: "Référentiel non trouvé")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/referentiels/{id}/fichier",
    post: new OA\Post(
        operationId: "referentiels-uploader-fichier",
        summary: "Uploader un fichier pour un référentiel",
        description: "Upload un fichier PDF/DOC/DOCX pour un référentiel existant et extrait son contenu",
        tags: ["Référentiels"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID du référentiel",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    type: "object",
                    required: ["fichier"],
                    properties: [
                        new OA\Property(
                            property: "fichier",
                            type: "string",
                            format: "binary",
                            description: "Fichier PDF/DOC/DOCX (max 50MB)"
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Fichier uploadé avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Fichier uploadé (15420 chars extraits). Indexation en cours."),
                        new OA\Property(property: "extraction_vide", type: "boolean", example: false),
                        new OA\Property(property: "referentiel", ref: "#/components/schemas/Referentiel")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé"),
            new OA\Response(response: 404, description: "Référentiel non trouvé"),
            new OA\Response(response: 422, description: "Erreur de validation du fichier")
        ]
    )
)]


class ReferentielController extends Controller
{
    public function __construct(
        private ExtractorFactory $extractorFactory,
    ) {}

    public function index(Request $request): JsonResponse
    {
        // L'etape « Referentiels » de Nouvelle Analyse filtre cote front en
        // comparant referentiel.secteursActivite vs client.secteursActivite ;
        // il faut donc eager-loader la relation, sinon tous les referentiels
        // apparaissent « transversaux » et la liste n'est jamais filtree.
        $query = Referentiel::query()
            ->with('uploadeur:id,nom,prenom', 'media', 'secteursActivite:id,nom')
            ->withCount('chunks');

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('secteur')) {
            $query->pourSecteur($request->secteur);
        }
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('titre', 'ilike', "%{$q}%")
                    ->orWhere('code', 'ilike', "%{$q}%")
                    ->orWhere('autorite', 'ilike', "%{$q}%");
            });
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function show(Referentiel $referentiel): JsonResponse
    {
        $referentiel->load('uploadeur:id,nom,prenom')
            ->loadCount('chunks');

        return response()->json(['referentiel' => $referentiel]);
    }

    public function store(Request $request): JsonResponse
    {
        // Debug: afficher le MIME type si un fichier est présent
        if ($request->hasFile('fichier')) {
            $fichier = $request->file('fichier');
            \Log::info('STORE - MIME type détecté: ' . $fichier->getMimeType());
            \Log::info('STORE - Extension originale: ' . $fichier->getClientOriginalExtension());
        }
        
        $data = $request->validate([
            'code' => 'required|string|max:64|unique:referentiels,code',
            'titre' => 'required|string|max:255',
            'description' => 'nullable|string',
            'autorite' => 'nullable|string|max:255',
            'version' => 'nullable|string|max:32',
            'date_publication' => 'nullable|date',
            'date_entree_vigueur' => 'nullable|date',
            'type' => 'required|in:loi,decret,arrete,directive,norme,guide,autre',
            'secteurs_activite_ids' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (is_null($value) || (is_array($value) && empty($value)) || $value === '') {
                        $fail('Au moins un secteur d\'activite doit etre selectionne.');
                        return;
                    }

                    // Normaliser en tableau de int (FormData envoie des strings)
                    if (is_string($value)) {
                        $items = array_map('trim', explode(',', $value));
                    } elseif (is_array($value)) {
                        $items = $value;
                    } else {
                        $fail('Le format des secteurs d\'activité est invalide.');
                        return false;
                    }

                    $ids = [];
                    foreach ($items as $raw) {
                        if (! is_numeric($raw)) {
                            $fail('Les IDs des secteurs d\'activité doivent être des entiers positifs.');
                            return false;
                        }
                        $id = (int) $raw;
                        if ($id <= 0) {
                            $fail('Les IDs des secteurs d\'activité doivent être des entiers positifs.');
                            return false;
                        }
                        $ids[] = $id;
                    }

                    foreach ($ids as $id) {
                        if (! \App\Models\SecteurActivite::where('id', $id)->exists()) {
                            $fail("Le secteur d'activité avec l'ID {$id} n'existe pas.");
                            return false;
                        }
                    }

                    return true;
                }
            ],
            'fichier' => [
                'nullable',
                'file',
                'max:51200',
                function ($attribute, $value, $fail) {
                    $extension = strtolower($value->getClientOriginalExtension());
                    $allowedExtensions = ['pdf', 'doc', 'docx'];
                    
                    if (!in_array($extension, $allowedExtensions)) {
                        $fail('Le fichier doit être au format PDF, DOC ou DOCX.');
                    }
                }
            ],
        ]);

        $data['created_by'] = $request->user()->id;
        $data['statut'] = 'actif';

        // Extraire les secteurs d'activité pour la relation
        $secteursActiviteIds = $this->traiterSecteursActiviteIds($data['secteurs_activite_ids'] ?? []);
        unset($data['secteurs_activite_ids']);

        $referentiel = Referentiel::create($data);

        // Synchroniser les secteurs d'activité
        if (! empty($secteursActiviteIds)) {
            $referentiel->secteursActivite()->sync($secteursActiviteIds);
        }

        if ($request->hasFile('fichier')) {
            $fichier = $request->file('fichier');
            $referentiel->addMedia($fichier)->toMediaCollection('fichiers');
            
            // Générer automatiquement source_url avec le chemin réel du fichier uploadé
            $media = $referentiel->getFirstMedia('fichiers');
            if ($media) {
                $referentiel->update(['source_url' => $media->getUrl()]);
            }

            try {
                $media = $referentiel->getFirstMedia('fichiers');
                if ($this->extractorFactory->supporte($media->mime_type)) {
                    $contenu = $this->extractorFactory->extraire($media->getPath(), $media->mime_type);
                    $referentiel->update(['contenu_extrait' => $contenu]);
                }
            } catch (\Throwable $e) {
                // Re-tente dans le job
            }

            IndexReferentielJob::dispatchAfterResponse($referentiel);
        }

        return response()->json(['referentiel' => $referentiel->load('secteursActivite:id,nom,code')], 201);
    }

    public function update(Request $request, Referentiel $referentiel): JsonResponse
    {
        $data = $request->validate([
            'titre' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'autorite' => 'nullable|string|max:255',
            'version' => 'nullable|string|max:32',
            'date_publication' => 'nullable|date',
            'date_entree_vigueur' => 'nullable|date',
            'type' => 'sometimes|in:loi,decret,arrete,directive,norme,guide,autre',
            'secteurs_activite_ids' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if (is_null($value)) {
                        return true;
                    }
                    
                    // Normaliser en tableau de int (FormData envoie des strings)
                    if (is_string($value)) {
                        $items = array_map('trim', explode(',', $value));
                    } elseif (is_array($value)) {
                        $items = $value;
                    } else {
                        $fail('Le format des secteurs d\'activité est invalide.');
                        return false;
                    }

                    $ids = [];
                    foreach ($items as $raw) {
                        if (! is_numeric($raw)) {
                            $fail('Les IDs des secteurs d\'activité doivent être des entiers positifs.');
                            return false;
                        }
                        $id = (int) $raw;
                        if ($id <= 0) {
                            $fail('Les IDs des secteurs d\'activité doivent être des entiers positifs.');
                            return false;
                        }
                        $ids[] = $id;
                    }

                    foreach ($ids as $id) {
                        if (! \App\Models\SecteurActivite::where('id', $id)->exists()) {
                            $fail("Le secteur d'activité avec l'ID {$id} n'existe pas.");
                            return false;
                        }
                    }

                    return true;
                }
            ],
            'statut' => 'sometimes|in:actif,obsolete,brouillon',
            'fichier' => [
                'nullable',
                'file',
                'max:51200',
                function ($attribute, $value, $fail) {
                    $extension = strtolower($value->getClientOriginalExtension());
                    $allowedExtensions = ['pdf', 'doc', 'docx'];
                    
                    if (!in_array($extension, $allowedExtensions)) {
                        $fail('Le fichier doit être au format PDF, DOC ou DOCX.');
                    }
                }
            ],
        ]);

        // Gérer les secteurs d'activité
        $secteursActiviteIds = $this->traiterSecteursActiviteIds($data['secteurs_activite_ids'] ?? null);
        unset($data['secteurs_activite_ids']);

        $data['updated_by'] = $request->user()->id;
        $referentiel->update($data);

        // Gérer le fichier si uploadé
        if ($request->hasFile('fichier')) {
            $fichier = $request->file('fichier');
            $referentiel->clearMediaCollection('fichiers');
            $referentiel->addMedia($fichier)->toMediaCollection('fichiers');
        }

        // Mettre à jour les secteurs d'activité si fournis
        if ($secteursActiviteIds !== null) {
            $referentiel->secteursActivite()->sync($secteursActiviteIds);
        }

        // Générer automatiquement source_url si le référentiel a un fichier
        $media = $referentiel->getFirstMedia('fichiers');
        if ($media && empty($data['source_url'])) {
            $referentiel->update(['source_url' => $media->getUrl()]);
        }

        return response()->json(['referentiel' => $referentiel->fresh()->load('secteursActivite:id,nom,code')]);
    }

    public function destroy(Referentiel $referentiel): JsonResponse
    {
        $referentiel->update(['deleted_by' => $request->user()->id]);
        $referentiel->delete();

        return response()->json(['message' => 'Referentiel supprime.']);
    }

    /**
     * Re-indexe un referentiel (re-extraction + re-chunking + re-embedding).
     * Execution synchrone : la reponse n'est renvoyee qu'une fois les chunks
     * crees. Sur PHP built-in server, ca bloque le thread le temps de
     * l'indexation — voulu, pour eviter le faux « tourne a l'infini »
     * provoque par un job de queue jamais consomme.
     */
    public function reindexer(Referentiel $referentiel): JsonResponse
    {
        IndexReferentielJob::dispatchSync($referentiel);

        $chunks = $referentiel->chunks()->count();

        return response()->json([
            'message' => "Re-indexation terminee : {$chunks} chunks crees.",
            'chunks_count' => $chunks,
        ]);
    }

    /**
     * Upload ou remplace le fichier d'un referentiel existant.
     * Detecte les PDF non textuels (scannes) et renvoie un avertissement clair.
     */
    public function uploaderFichier(Request $request, Referentiel $referentiel): JsonResponse
    {
        $fichier = $request->file('fichier');
        
        // Debug: afficher le MIME type détecté
        \Log::info('MIME type détecté: ' . $fichier->getMimeType());
        \Log::info('Extension originale: ' . $fichier->getClientOriginalExtension());
        
        $request->validate([
            'fichier' => [
                'required',
                'file',
                'max:51200',
                function ($attribute, $value, $fail) {
                    $extension = strtolower($value->getClientOriginalExtension());
                    $allowedExtensions = ['pdf', 'doc', 'docx'];
                    
                    if (!in_array($extension, $allowedExtensions)) {
                        $fail('Le fichier doit être au format PDF, DOC ou DOCX.');
                    }
                }
            ],
        ]);

        $referentiel->clearMediaCollection('fichiers');
        $referentiel->addMedia($fichier)->toMediaCollection('fichiers');

        // Générer automatiquement source_url avec le chemin réel du fichier uploadé
        $media = $referentiel->getFirstMedia('fichiers');
        if ($media) {
            $referentiel->update(['source_url' => $media->getUrl()]);
        }

        $contenu = '';
        try {
            $media = $referentiel->getFirstMedia('fichiers');
            if ($media && $this->extractorFactory->supporte($media->mime_type)) {
                $contenu = $this->extractorFactory->extraire($media->getPath(), $media->mime_type);
            }
        } catch (\Throwable $e) {
            // On continue — l'utilisateur peut saisir le texte manuellement
        }

        $referentiel->update(['contenu_extrait' => $contenu ?: null]);
        $referentiel->chunks()->delete();

        if (empty(trim($contenu))) {
            return response()->json([
                'message' => 'Fichier uploade mais aucun texte extractible (PDF scanne ?). Utilisez « Saisir le texte » pour coller le contenu manuellement.',
                'extraction_vide' => true,
                'referentiel' => $referentiel->fresh(),
            ], 200);
        }

        // Synchrone : on attend que les chunks soient crees pour pouvoir
        // confirmer la reussite a l'utilisateur dans la meme reponse HTTP.
        IndexReferentielJob::dispatchSync($referentiel);

        $chunks = $referentiel->chunks()->count();

        return response()->json([
            'message' => 'Fichier uploade (' . strlen($contenu) . " chars extraits, {$chunks} chunks indexes).",
            'extraction_vide' => false,
            'referentiel' => $referentiel->fresh(),
        ]);
    }

    
    /**
     * Traite le champ secteurs_activite_ids pour gérer les tableaux et les chaînes de caractères
     * Convertit "1,2,3" en [1, 2, 3]
     */
    private function traiterSecteursActiviteIds($secteursActiviteIds): ?array
    {
        if (is_string($secteursActiviteIds)) {
            $secteursActiviteIds = explode(',', $secteursActiviteIds);
            $secteursActiviteIds = array_map('trim', $secteursActiviteIds);
            $secteursActiviteIds = array_filter($secteursActiviteIds, 'is_numeric');
            $secteursActiviteIds = array_map('intval', $secteursActiviteIds);
        }
        
        return $secteursActiviteIds;
    }
}
