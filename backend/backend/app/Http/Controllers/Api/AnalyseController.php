<?php

/**
 * Controleur AnalyseController — Lancement et consultation des analyses d'ecarts.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyserMissionJob;
use App\Models\Analyse;
use App\Models\AnalyseVersion;
use App\Models\Document;
use App\Models\Ecart;
use App\Models\Mission;
use App\Models\Referentiel;
use App\Services\Analyse\RapportPptxGenerator;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[OA\PathItem(
    path: "/api/analyses",
    get: new OA\Get(
        operationId: "analyses-index",
        summary: "Lister toutes les analyses",
        description: "Retourne la liste paginée des analyses d'écarts avec filtres optionnels",
        tags: ["Analyses"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "mission_id",
                in: "query",
                description: "Filtrer par mission",
                required: false,
                schema: new OA\Schema(type: "integer")
            ),
            new OA\Parameter(
                name: "statut",
                in: "query",
                description: "Filtrer par statut (en_cours, termine, erreur)",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["en_cours", "termine", "erreur"])
            ),
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
                schema: new OA\Schema(type: "integer", default: 15)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des analyses",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/Analyse")
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
    ),
    post: new OA\Post(
        operationId: "analyses-store",
        summary: "Lancer une nouvelle analyse",
        description: "Démarre une analyse d'écarts sur une mission avec les référentiels et documents sélectionnés",
        tags: ["Analyses"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["mission_id", "referentiels_ids"],
                properties: [
                    new OA\Property(property: "mission_id", type: "integer", example: 1),
                    new OA\Property(property: "titre", type: "string", nullable: true, example: "Analyse RGPD - Q1 2024"),
                    new OA\Property(property: "referentiels_ids", type: "array", items: new OA\Items(type: "integer"), example: [1, 2, 3]),
                    new OA\Property(property: "documents_ids", type: "array", items: new OA\Items(type: "integer"), nullable: true, example: [1, 2]),
                    new OA\Property(property: "questionnaires_ids", type: "array", items: new OA\Items(type: "integer"), nullable: true, example: [1]),
                    new OA\Property(property: "enrichissement_ia", type: "boolean", nullable: true, example: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Analyse lancée avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Analyse lancée."),
                        new OA\Property(property: "analyse", ref: "#/components/schemas/Analyse")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé (réservé aux non-clients)"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/analyses/{id}",
    get: new OA\Get(
        operationId: "analyses-show",
        summary: "Afficher une analyse",
        description: "Retourne les détails complets d'une analyse avec ses écarts et rapports",
        tags: ["Analyses"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID de l'analyse",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Détails de l'analyse",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "analyse", ref: "#/components/schemas/Analyse"),
                        new OA\Property(property: "referentiels", type: "array", items: new OA\Items(ref: "#/components/schemas/Referentiel")),
                        new OA\Property(property: "documents", type: "array", items: new OA\Items(ref: "#/components/schemas/Document")),
                        new OA\Property(property: "rapport_disponible", type: "boolean", example: true)
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Accès non autorisé"),
            new OA\Response(response: 404, description: "Analyse non trouvée")
        ]
    )
)]

class AnalyseController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private RapportPptxGenerator $rapportGenerator,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Analyse::query()
            ->with(['mission:id,reference,titre,client_id', 'mission.client:id,raison_sociale', 'lanceur:id,nom,prenom'])
            ->withCount('ecarts');

        if ($request->filled('mission_id')) {
            $query->where('mission_id', $request->mission_id);
        }
        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }
        if ($request->filled('client_id')) {
            $query->whereHas('mission', fn ($q) => $q->where('client_id', $request->client_id));
        }

        // User sans permission "voir toutes les analyses" : scoper aux entreprises rattachees.
        // Bascule de hasRole('client') vers permissions : supporte les nouveaux roles dynamiques
        // (un admin peut creer un role "Auditeur externe" avec view-analyses, il verra tout).
        $user = $request->user();
        if (! $user->hasAnyPermission(['view-analyses', 'view-all-analyses'])) {
            $clientIds = $user->clients()->pluck('clients.id');
            $query->whereHas('mission', fn ($q) => $q->whereIn('client_id', $clientIds));
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function show(Request $request, Analyse $analyse): JsonResponse
    {
        $this->verifierAccesClient($request->user(), $analyse);

        $analyse->load([
            'mission.client',
            'lanceur:id,nom,prenom',
            'ecarts' => fn ($q) => $q->orderByRaw("CASE gravite WHEN 'critique' THEN 1 WHEN 'majeur' THEN 2 WHEN 'mineur' THEN 3 ELSE 4 END")
                ->with(['referentiel:id,code,titre', 'document:id,titre', 'assigne:id,nom,prenom', 'preuves.uploadeur:id,nom,prenom']),
        ]);

        return response()->json([
            'analyse' => $analyse,
            'referentiels' => $analyse->referentiels(),
            'documents' => $analyse->documents(),
            'rapport_disponible' => ! empty($analyse->rapport_word_path) && Storage::disk('local')->exists($analyse->rapport_word_path),
        ]);
    }

    /**
     * Lance une nouvelle analyse sur une mission.
     *
     * Prerequis assouplis : la matrice de collecte et les questionnaires ne sont
     * plus obligatoires. Si aucun document n'est fourni, le moteur prend par
     * defaut TOUS les documents du client. Si aucun referentiel n'est fourni,
     * on filtre automatiquement sur le secteur d'activite du client.
     */
    public function store(Request $request): JsonResponse
    {
        $this->verifierNonClient($request->user());

        $data = $request->validate([
            'mission_id' => 'required|exists:missions,id',
            'titre' => 'nullable|string|max:255',
            'referentiels_ids' => 'nullable|array',
            'referentiels_ids.*' => 'exists:referentiels,id',
            'documents_ids' => 'nullable|array',
            'documents_ids.*' => 'exists:documents,id',
            'questionnaires_ids' => 'nullable|array',
            'questionnaires_ids.*' => 'exists:questionnaires_generes,id',
            'enrichissement_ia' => 'nullable|boolean',
        ]);

        $mission = Mission::findOrFail($data['mission_id']);

        $referentielsIds = $this->resoudreReferentiels($mission, $data['referentiels_ids'] ?? []);
        if (empty($referentielsIds)) {
            return response()->json([
                'message' => 'Aucun referentiel applicable au secteur d\'activite du client. Selectionnez-en au moins un manuellement.',
            ], 422);
        }

        $documentsIds = $this->resoudreDocuments($mission, $data['documents_ids'] ?? []);

        $enrichissementIa = (bool) ($data['enrichissement_ia'] ?? false);

        $analyse = Analyse::create([
            'mission_id' => $mission->id,
            'lancee_par' => $request->user()->id,
            'created_by' => $request->user()->id,
            'reference' => Analyse::genererReference(),
            'titre' => $data['titre'] ?? ('Analyse conformite - ' . now()->format('d/m/Y H:i')),
            'statut' => 'en_attente',
            'referentiels_ids' => $referentielsIds,
            'documents_ids' => $documentsIds,
            'questionnaires_ids' => $data['questionnaires_ids'] ?? [],
            'enrichissement_ia' => $enrichissementIa,
        ]);

        // Mode "Rapide" (~30s) : on execute le job juste apres avoir renvoye la
        // reponse HTTP, dans le meme process PHP. Plus besoin d'un worker
        // `php artisan queue:work` pour ce cas — la barre de progression suit
        // l'avancement via le polling cote front.
        //
        // Mode "Enrichi IA" (20+ min, appels LLM nombreux) : on dispatch sur
        // la queue. Un worker `php artisan queue:work --queue=analyses` doit
        // tourner en parallele du serveur web (terminal separe). Sinon le job
        // reste en file et la progression bloque a 0%. Le worker isole le
        // long process de la requete HTTP — l'app reste reactive.
        if ($enrichissementIa) {
            AnalyserMissionJob::dispatch($analyse);
        } else {
            AnalyserMissionJob::dispatchAfterResponse($analyse);
        }

        return response()->json([
            'analyse' => $analyse,
            'message' => 'Analyse lancee. Le traitement est en cours.',
        ], 201);
    }

    public function destroy(Request $request, Analyse $analyse): JsonResponse
    {
        $this->verifierNonClient($request->user());

        if ($analyse->rapport_word_path && Storage::disk('local')->exists($analyse->rapport_word_path)) {
            Storage::disk('local')->delete($analyse->rapport_word_path);
        }
        $analyse->delete();

        return response()->json(['message' => 'Analyse supprimee.']);
    }

    /**
     * Bloque l'utilisateur qui n'a aucune permission d'ecriture sur les analyses.
     * (Anciennement : bloquait par nom de role "client". Bascule en permissions pour
     * supporter les roles dynamiques crees par l'admin via /admin/roles.)
     */
    private function verifierNonClient($user): void
    {
        if ($user && ! $user->hasAnyPermission(['create-analyses', 'update-analyses', 'delete-analyses', 'view-all-analyses'])) {
            abort(403, 'Action non autorisee : aucune permission d\'ecriture sur les analyses.');
        }
    }

    /**
     * Bloque l'acces a une analyse si l'utilisateur n'a pas la permission de voir toutes
     * les analyses ET que l'analyse n'appartient pas a une entreprise qui lui est rattachee.
     */
    private function verifierAccesClient($user, Analyse $analyse): void
    {
        if (! $user || $user->hasAnyPermission(['view-analyses', 'view-all-analyses'])) {
            return;
        }
        $clientIds = $user->clients()->pluck('clients.id');
        if (! $clientIds->contains($analyse->mission?->client_id)) {
            abort(403, 'Acces refuse : cette analyse ne vous concerne pas.');
        }
    }

    /**
     * Filtre/complete la liste des referentiels :
     *   - Si l'utilisateur en a fourni explicitement, on les respecte (priorite UI).
     *   - Sinon on prend les referentiels rattaches aux secteurs d'activite du client
     *     de la mission. Si le client n'a pas de secteur, on prend tout.
     *
     * @param  array<int>  $referentielsIdsFournis
     * @return array<int>
     */
    private function resoudreReferentiels(Mission $mission, array $referentielsIdsFournis): array
    {
        if (! empty($referentielsIdsFournis)) {
            return $referentielsIdsFournis;
        }

        $client = $mission->client;
        if (! $client) {
            return Referentiel::pluck('id')->all();
        }

        $secteurIds = $client->secteursActivite()->pluck('secteurs_activite.id');
        if ($secteurIds->isEmpty()) {
            return Referentiel::pluck('id')->all();
        }

        return Referentiel::whereHas('secteursActivite', fn ($q) => $q->whereIn('secteurs_activite.id', $secteurIds))
            ->pluck('id')
            ->all();
    }

    /**
     * Complete la liste des documents :
     *   - Si l'utilisateur en a selectionne, on les respecte tels quels.
     *   - Sinon on prend TOUS les documents indexes du client (toutes missions
     *     confondues) pour permettre une analyse sans selection prealable.
     *
     * @param  array<int>  $documentsIdsFournis
     * @return array<int>
     */
    private function resoudreDocuments(Mission $mission, array $documentsIdsFournis): array
    {
        if (! empty($documentsIdsFournis)) {
            return $documentsIdsFournis;
        }

        $clientId = $mission->client_id;
        if (! $clientId) {
            return Document::where('mission_id', $mission->id)->pluck('id')->all();
        }

        return Document::query()
            ->whereHas('mission', fn ($q) => $q->where('client_id', $clientId))
            ->whereIn('statut', ['indexe', 'en_attente'])
            ->pluck('id')
            ->all();
    }

    /**
     * Sauvegarde un snapshot complet de l'analyse courante avant relance.
     */
    private function snapshotter(Analyse $analyse, int $auteurId, ?string $motif): AnalyseVersion
    {
        $numero = ($analyse->versions()->max('numero_version') ?? 0) + 1;

        $ecartsSnapshot = $analyse->ecarts()
            ->with(['referentiel:id,code,titre', 'document:id,titre', 'preuves'])
            ->get()
            ->map(fn (Ecart $e) => [
                'id' => $e->id,
                'gravite' => $e->gravite,
                'categorie' => $e->categorie,
                'type_ecart' => $e->type_ecart,
                'titre' => $e->titre,
                'exigence_referentiel' => $e->exigence_referentiel,
                'article_reference' => $e->article_reference,
                'description_ecart' => $e->description_ecart,
                'risque' => $e->risque,
                'recommandation' => $e->recommandation,
                'extrait_document' => $e->extrait_document,
                'documents_sources' => $e->documents_sources,
                'score_similarite' => $e->score_similarite,
                'statut_correction' => $e->statut_correction,
                'referentiel' => $e->referentiel ? [
                    'id' => $e->referentiel->id,
                    'code' => $e->referentiel->code,
                    'titre' => $e->referentiel->titre,
                ] : null,
                'document' => $e->document ? [
                    'id' => $e->document->id,
                    'titre' => $e->document->titre,
                ] : null,
                'preuves' => $e->preuves->map(fn ($p) => [
                    'id' => $p->id,
                    'libelle' => $p->libelle ?? null,
                    'chemin' => $p->chemin ?? null,
                    'mime' => $p->mime ?? null,
                ])->all(),
            ])
            ->all();

        $preuvesSnapshot = [];
        foreach ($ecartsSnapshot as $e) {
            foreach ($e['preuves'] as $preuve) {
                $preuvesSnapshot[] = ['ecart_id' => $e['id']] + $preuve;
            }
        }

        // Copie figee du rapport Word si present : on garde le chemin original ;
        // la suppression de l'analyse principale ne s'applique pas a la version.
        $rapportPath = null;
        if (! empty($analyse->rapport_word_path) && Storage::disk('local')->exists($analyse->rapport_word_path)) {
            $rapportPath = sprintf(
                'analyses/versions/%d/%d_%s',
                $analyse->id,
                $numero,
                basename($analyse->rapport_word_path),
            );
            Storage::disk('local')->copy($analyse->rapport_word_path, $rapportPath);
        }

        return AnalyseVersion::create([
            'analyse_id' => $analyse->id,
            'auteur_id' => $auteurId,
            'numero_version' => $numero,
            'motif' => $motif,
            'statut' => $analyse->statut,
            'score_conformite' => $analyse->score_conformite,
            'nb_exigences_total' => $analyse->nb_exigences_total ?? 0,
            'nb_exigences_verifiees' => $analyse->nb_exigences_verifiees ?? 0,
            'nb_ecarts_critiques' => $analyse->nb_ecarts_critiques ?? 0,
            'nb_ecarts_majeurs' => $analyse->nb_ecarts_majeurs ?? 0,
            'nb_ecarts_mineurs' => $analyse->nb_ecarts_mineurs ?? 0,
            'referentiels_ids' => $analyse->referentiels_ids,
            'documents_ids' => $analyse->documents_ids,
            'questionnaires_ids' => $analyse->questionnaires_ids,
            'synthese' => $analyse->synthese,
            'ecarts_snapshot' => $ecartsSnapshot,
            'preuves_snapshot' => $preuvesSnapshot,
            'rapport_word_path' => $rapportPath,
            'commentaire_ia' => $analyse->commentaire_ia,
            'demarree_a' => $analyse->demarree_a,
            'terminee_a' => $analyse->terminee_a,
        ]);
    }

    /**
     * Annule une analyse en cours.
     */
    public function annuler(Request $request, Analyse $analyse): JsonResponse
    {
        $this->verifierNonClient($request->user());

        if (! in_array($analyse->statut, ['en_attente', 'en_cours'])) {
            return response()->json(['message' => 'L\'analyse n\'est pas en cours.'], 422);
        }

        $analyse->update([
            'statut' => 'annulee',
            'terminee_a' => now(),
            'etape_courante' => 'Annulee par l\'utilisateur',
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Analyse annulee.', 'analyse' => $analyse->fresh()]);
    }

    /**
     * Enrichit les ecarts d'une analyse terminee avec l'IA (redaction titre/description/reco).
     * Peut etre lent selon Ollama.
     */
    public function enrichirIA(Request $request, Analyse $analyse): JsonResponse
    {
        $this->verifierNonClient($request->user());

        if ($analyse->statut !== 'terminee') {
            return response()->json(['message' => 'L\'analyse doit etre terminee pour etre enrichie.'], 422);
        }

        // Options de scope. Defaut UX : on n'enrichit QUE les ecarts critiques
        // (les majeurs et mineurs gardent leurs templates qui sont deja tres
        // lisibles). Sur llama3.2:3b CPU (~5 t/s), 6 critiques x ~60s = ~5 min,
        // contre 40+ min si on enrichit tous les majeurs aussi.
        $data = $request->validate([
            'include_majeurs' => 'nullable|boolean',
            'include_mineurs' => 'nullable|boolean',
        ]);
        $skipMajeurs = ! (bool) ($data['include_majeurs'] ?? false);
        $skipMineurs = ! (bool) ($data['include_mineurs'] ?? false);

        // Signaler immediatement a l'UI que l'enrichissement demarre
        $analyse->update([
            'etape_courante' => 'Enrichissement IA : en file...',
            'progression_pct' => 0,
            'enrichissement_annule' => false,
        ]);

        \App\Jobs\EnrichirEcartsJob::dispatchAfterResponse($analyse, $skipMineurs, $skipMajeurs);

        return response()->json([
            'message' => 'Enrichissement IA lance en arriere-plan.',
            'analyse' => $analyse->fresh(),
        ]);
    }

    /**
     * Annule un enrichissement IA en cours. Le job detecte le flag
     * a la prochaine iteration (apres l'ecart courant) et sort proprement.
     */
    public function annulerEnrichissement(Request $request, Analyse $analyse): JsonResponse
    {
        $this->verifierNonClient($request->user());

        $analyse->update([
            'enrichissement_annule' => true,
            'etape_courante' => 'Enrichissement IA : annulation demandee...',
        ]);

        return response()->json([
            'message' => 'Annulation demandee. L\'enrichissement s\'arretera apres l\'ecart en cours.',
            'analyse' => $analyse->fresh(),
        ]);
    }

    /**
     * Relance une analyse en erreur ou annulee (ou meme terminee pour refaire).
     */
    public function relancer(Request $request, Analyse $analyse): JsonResponse
    {
        $this->verifierNonClient($request->user());

        // Le mode peut etre force au relancer : par defaut on garde celui de
        // l'analyse, mais on accepte enrichissement_ia=true/false pour basculer
        // (utile quand l'analyse initiale en mode "enrichi IA" est trop lente
        // a refaire et qu'on prefere une relance rapide).
        $data = $request->validate(['enrichissement_ia' => 'nullable|boolean']);
        $modeIa = array_key_exists('enrichissement_ia', $data) ? (bool) $data['enrichissement_ia'] : null;

        $analyse->ecarts()->delete();
        $patch = [
            'statut' => 'en_attente',
            'demarree_a' => null,
            'terminee_a' => null,
            'erreur_message' => null,
            'nb_exigences_verifiees' => 0,
            'nb_ecarts_critiques' => 0,
            'nb_ecarts_majeurs' => 0,
            'nb_ecarts_mineurs' => 0,
            'score_conformite' => null,
            'progression_pct' => 0,
            'etape_courante' => 'Remise en file',
            'rapport_word_path' => null,
        ];
        if ($modeIa !== null) {
            $patch['enrichissement_ia'] = $modeIa;
        }
        $analyse->update($patch);
        $analyse->refresh();

        // Meme strategie que store() : queue pour enrichi (necessite worker),
        // dispatchAfterResponse pour rapide (instantane, sans worker).
        if ($analyse->enrichissement_ia) {
            \App\Jobs\AnalyserMissionJob::dispatch($analyse);
        } else {
            \App\Jobs\AnalyserMissionJob::dispatchAfterResponse($analyse);
        }

        return response()->json([
            'message' => $analyse->enrichissement_ia
                ? 'Analyse relancee en mode enrichi IA (peut prendre plusieurs minutes).'
                : 'Analyse relancee en mode rapide (≈ 30 s).',
            'analyse' => $analyse,
        ]);
    }

    /**
     * Regenere le rapport Word a la demande.
     */
    public function regenererRapport(Request $request, Analyse $analyse): JsonResponse
    {
        $this->verifierNonClient($request->user());

        if ($analyse->statut !== 'terminee') {
            return response()->json(['message' => 'L\'analyse n\'est pas terminee.'], 422);
        }

        $chemin = $this->rapportGenerator->generer($analyse);

        return response()->json([
            'message' => 'Rapport regenere avec succes.',
            'rapport_word_path' => $chemin,
        ]);
    }

    /**
     * Refait une analyse : conserve un snapshot complet de la version courante
     * dans analyse_versions, puis relance le moteur. Permet au client
     * d'apporter ses corrections (preuves, questionnaires, documents
     * complementaires) et d'obtenir une nouvelle version du rapport tout en
     * gardant l'historique.
     */
    public function refaire(Request $request, Analyse $analyse): JsonResponse
    {
        $this->verifierNonClient($request->user());

        if (! in_array($analyse->statut, ['terminee', 'erreur', 'annulee'], true)) {
            return response()->json([
                'message' => 'Impossible de refaire une analyse encore en cours.',
            ], 422);
        }

        $data = $request->validate([
            'motif' => 'nullable|string|max:255',
        ]);

        $this->snapshotter($analyse, $request->user()->id, $data['motif'] ?? null);

        // Re-resoudre les sources : on profite du refaire pour inclure les
        // eventuels nouveaux documents uploades depuis la derniere analyse.
        $mission = $analyse->mission;
        if ($mission) {
            $documentsIds = $this->resoudreDocuments($mission, $analyse->documents_ids ?? []);
            $analyse->update(['documents_ids' => $documentsIds]);
        }

        $analyse->ecarts()->delete();
        $analyse->update([
            'statut' => 'en_attente',
            'demarree_a' => null,
            'terminee_a' => null,
            'erreur_message' => null,
            'nb_exigences_verifiees' => 0,
            'nb_ecarts_critiques' => 0,
            'nb_ecarts_majeurs' => 0,
            'nb_ecarts_mineurs' => 0,
            'score_conformite' => null,
            'progression_pct' => 0,
            'etape_courante' => 'Remise en file (nouvelle version)',
            'rapport_word_path' => null,
        ]);

        // Meme strategie que store() : queue pour enrichi, dispatchAfterResponse
        // pour rapide. Le worker queue:work doit tourner pour le mode enrichi.
        if ($analyse->enrichissement_ia) {
            AnalyserMissionJob::dispatch($analyse);
        } else {
            AnalyserMissionJob::dispatchAfterResponse($analyse);
        }

        return response()->json([
            'message' => 'Nouvelle version de l\'analyse lancee. La version precedente a ete archivee.',
            'analyse' => $analyse->fresh(),
        ]);
    }

    /**
     * Liste les versions historiques d'une analyse (snapshot avant chaque
     * relance via refaire()).
     */
    public function versions(Request $request, Analyse $analyse): JsonResponse
    {
        $this->verifierAccesClient($request->user(), $analyse);

        $versions = $analyse->versions()
            ->with('auteur:id,nom,prenom')
            ->get();

        return response()->json(['data' => $versions]);
    }

    /**
     * Detaille une version specifique (incluant les ecarts snapshot).
     */
    public function versionShow(Request $request, Analyse $analyse, AnalyseVersion $version): JsonResponse
    {
        $this->verifierAccesClient($request->user(), $analyse);

        if ($version->analyse_id !== $analyse->id) {
            abort(404);
        }

        $version->load('auteur:id,nom,prenom');

        return response()->json(['version' => $version]);
    }

    /**
     * Telecharge le rapport PowerPoint (.pptx) de l'analyse.
     */
    public function telechargerRapport(Request $request, Analyse $analyse): BinaryFileResponse
    {
        $this->verifierAccesClient($request->user(), $analyse);

        if (empty($analyse->rapport_word_path) || ! Storage::disk('local')->exists($analyse->rapport_word_path)) {
            abort(404, 'Rapport non disponible. Veuillez le regenerer.');
        }

        $cheminAbsolu = Storage::disk('local')->path($analyse->rapport_word_path);
        $nomFichier = basename($analyse->rapport_word_path);
        $extension = strtolower(pathinfo($nomFichier, PATHINFO_EXTENSION));

        $mimes = [
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        return response()->download(
            $cheminAbsolu,
            $nomFichier,
            ['Content-Type' => $mimes[$extension] ?? 'application/octet-stream']
        );
    }
}
