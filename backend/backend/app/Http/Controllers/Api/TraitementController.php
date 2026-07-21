<?php

/**
 * Controleur TraitementController — CRUD des fiches MOBISOFT.
 *
 * Le payload accepte `traitement` (champs maitres) + 6 collections
 * imbriquees synchronisees a chaque save :
 *   supports[], actes[], personnes[], categoriesDonnees[], transferts[],
 *   mesuresSecurite[].
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Traitement;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Illuminate\Support\Facades\DB;

#[OA\PathItem(
    path: "/api/traitements",
    get: new OA\Get(
        operationId: "traitements-index",
        summary: "Lister les traitements",
        description: "Retourne la liste paginée des traitements avec leurs collections imbriquées",
        tags: ["Traitements"],
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
                name: "statut",
                in: "query",
                description: "Filtrer par statut",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["brouillon", "en_cours", "valide", "archive"])
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
                description: "Liste des traitements",
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
                                    new OA\Property(property: "nom", type: "string"),
                                    new OA\Property(property: "finalite", type: "string"),
                                    new OA\Property(property: "statut", type: "string", enum: ["brouillon", "en_cours", "valide", "archive"]),
                                    new OA\Property(property: "client", type: "object", properties: [
                                        new OA\Property(property: "id", type: "integer"),
                                        new OA\Property(property: "raison_sociale", type: "string")
                                    ]),
                                    new OA\Property(property: "supports_count", type: "integer"),
                                    new OA\Property(property: "categoriesDonnees_count", type: "integer"),
                                    new OA\Property(property: "transferts_count", type: "integer")
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

class TraitementController extends Controller
{
    public function __construct(
        private AuditService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Traitement::query()
            ->with(['client:id,raison_sociale', 'saisiPar:id,nom,prenom', 'validePar:id,nom,prenom'])
            ->withCount(['supports', 'categoriesDonnees', 'transferts']);

        // User sans permission de gerer tous les traitements : scoper a ses propres clients.
        if (! $user->hasPermissionTo('view-all-traitements')) {
            $clientIds = $user->clients()->pluck('clients.id');
            $query->whereIn('client_id', $clientIds);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(fn ($sub) => $sub
                ->where('designation', 'ilike', "%{$q}%")
                ->orWhere('reference', 'ilike', "%{$q}%")
                ->orWhere('description', 'ilike', "%{$q}%"));
        }

        // per_page respecte (cap a 500 pour eviter les requetes excessives).
        // Le frontend envoie 100-200 pour permettre des stats globales correctes
        // (compteurs Total / Brouillons / Validés / Sensibles / Hors CEDEAO).
        $perPage = min(500, max(1, (int) $request->input('per_page', 20)));

        return response()->json($query->latest()->paginate($perPage));
    }

    public function show(Request $request, Traitement $traitement): JsonResponse
    {
        $traitement->load([
            'client:id,raison_sociale',
            'client.secteursActivite:id,nom',
            'saisiPar:id,nom,prenom',
            'validePar:id,nom,prenom',
            'supports', 'actes', 'personnes', 'categoriesDonnees', 'transferts', 'mesuresSecurite',
        ]);

        return response()->json([
            'traitement' => $traitement,
            'peut_modifier' => $traitement->statut !== 'archive',
            'peut_valider' => $traitement->statut === 'brouillon',
            'peut_archiver' => $traitement->statut === 'valide',
            'peut_supprimer' => $request->user()->hasPermissionTo('delete-traitements'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validerDonnees($request);
        $user = $request->user();

        $clientId = $data['client_id'] ?? $user->clients()->value('clients.id');
        abort_if(! $clientId, 422, 'Aucune entreprise rattachee a votre compte.');

        $traitement = DB::transaction(function () use ($data, $user, $clientId) {
            $maitre = $this->extraireChampsMaitres($data);
            $maitre['client_id'] = $clientId;
            $maitre['saisi_par'] = $user->id;
            $maitre['reference'] = Traitement::genererReference($clientId);
            $maitre['statut'] = 'brouillon';
            $maitre['date_creation_fiche'] = now()->toDateString();
            // Egale a la date de creation a l'init, puis ecrasee a chaque
            // update. Cela evite une cellule vide dans la fiche MOBISOFT
            // pour les traitements jamais modifies.
            $maitre['date_maj_fiche'] = now()->toDateString();

            $t = Traitement::create($maitre);
            $this->synchroniserSousTables($t, $data);

            return $t;
        });

        $this->audit->log('traitement.cree', ['traitement_id' => $traitement->id]);

        return response()->json([
            'traitement' => $this->charger($traitement),
            'message' => 'Traitement cree en mode brouillon.',
        ], 201);
    }

    public function update(Request $request, Traitement $traitement): JsonResponse
    {
        $data = $this->validerDonnees($request, partiel: true);
        $avantStatut = $traitement->statut;

        DB::transaction(function () use ($traitement, $data) {
            $maitre = $this->extraireChampsMaitres($data);
            if ($traitement->statut === 'valide') {
                $maitre['statut'] = 'brouillon';
                $maitre['valide_par'] = null;
                $maitre['valide_at'] = null;
            }
            $maitre['date_maj_fiche'] = now()->toDateString();
            $traitement->update($maitre);
            $this->synchroniserSousTables($traitement, $data);
        });

        $this->audit->log('traitement.modifie', [
            'traitement_id' => $traitement->id,
            'statut_avant' => $avantStatut,
        ]);

        return response()->json([
            'traitement' => $this->charger($traitement->fresh()),
            'message' => $avantStatut === 'valide'
                ? 'Traitement modifie. Il est repasse en brouillon et doit etre revalide.'
                : 'Traitement mis a jour.',
        ]);
    }

    public function valider(Request $request, Traitement $traitement): JsonResponse
    {
        if (empty($traitement->designation) || empty($traitement->description)) {
            return response()->json(['message' => 'Designation et description sont requises pour valider.'], 422);
        }
        if ($traitement->categoriesDonnees()->count() === 0) {
            return response()->json(['message' => 'Au moins une categorie de donnees doit etre renseignee.'], 422);
        }

        $traitement->update([
            'statut' => 'valide',
            'valide_par' => $request->user()->id,
            'valide_at' => now(),
        ]);

        $this->audit->log('traitement.valide', ['traitement_id' => $traitement->id]);

        return response()->json([
            'traitement' => $this->charger($traitement->fresh()),
            'message' => 'Traitement valide avec succes.',
        ]);
    }

    public function archiver(Request $request, Traitement $traitement): JsonResponse
    {
        $traitement->update(['statut' => 'archive']);
        $this->audit->log('traitement.archive', ['traitement_id' => $traitement->id]);

        return response()->json(['traitement' => $traitement->fresh(), 'message' => 'Traitement archive.']);
    }

    public function destroy(Request $request, Traitement $traitement): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('delete-traitements')) {
            abort(403, 'Permission delete-traitements requise.');
        }
        $traitement->delete();
        $this->audit->log('traitement.supprime', ['traitement_id' => $traitement->id]);

        return response()->json(['message' => 'Traitement supprime.']);
    }

    public function historique(Request $request, Traitement $traitement): JsonResponse
    {
        // Compatibilite : historique simplifie via activity log Spatie
        return response()->json([
            'traitement' => ['id' => $traitement->id, 'reference' => $traitement->reference, 'designation' => $traitement->designation],
            'timeline' => $traitement->activities()->latest()->take(50)->get()->map(fn ($a) => [
                'date' => $a->created_at,
                'description' => $a->description,
                'changements' => $a->properties,
            ]),
        ]);
    }

    /**
     * Pre-remplit les champs d'un nouveau traitement a partir des donnees
     * disponibles dans le profil du client et de son entreprise (Phase 5).
     *
     * Reduit la saisie manuelle : on renvoie au front un payload initial avec
     * client_id, direction_pole, services_charges, contact RT/DPO si dispo, etc.
     *
     * Strictement scope a l'entreprise de l'utilisateur connecte.
     */
    /**
     * Cree automatiquement des fiches de traitements (brouillons) a partir
     * des reponses aux questionnaires d'un client. Pour chaque questionnaire
     * publie et rempli :
     *   - on isole la reponse a la question "finalites de la collecte"
     *     (matching tolerant sur le texte de la question)
     *   - on decoupe cette reponse par retours a la ligne (chaque ligne = 1
     *     finalite a creer)
     *   - on cree un Traitement par finalite, pre-rempli avec :
     *       designation = finalite, direction_pole = pole du questionnaire,
     *       services_charges = service du questionnaire, sources = nom du formulaire
     *
     * Idempotent : si un traitement avec la meme designation + meme client
     * existe deja, il est ignore (pas de doublon).
     *
     * Retourne le compte de creations + la liste des designations sautees.
     */
    public function creerDepuisQuestionnaires(Request $request): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('create-traitements')) {
            abort(403, 'Permission create-traitements requise.');
        }
        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
        ]);

        $user = $request->user();
        $client = \App\Models\Client::findOrFail($data['client_id']);

        // Scope securite : si l'utilisateur n'est pas ASC, il ne peut creer
        // que pour sa propre entreprise.
        if (! $user->hasPermissionTo('view-all-traitements')) {
            $clientUserId = $user->clients()->where('clients.id', $client->id)->value('clients.id');
            abort_unless($clientUserId, 403, 'Ce client n\'est pas rattache a votre compte.');
        }

        $questionnaires = \App\Models\QuestionnaireGenere::whereHas('mission', fn ($q) => $q->where('client_id', $client->id))
            ->where('est_publie', true)
            ->get();

        $crees = [];
        $sautes = [];
        $finalitesDejaVues = [];
        $existants = $client->traitements()
            ->pluck('designation')
            ->map(fn ($d) => mb_strtolower(trim($d)))
            ->all();

        foreach ($questionnaires as $q) {
            $finalites = $this->extraireFinalitesDuQuestionnaire($q);
            if (empty($finalites)) continue;

            foreach ($finalites as $finalite) {
                $cle = mb_strtolower($finalite);
                if (in_array($cle, $existants, true) || isset($finalitesDejaVues[$cle])) {
                    $sautes[] = ['finalite' => $finalite, 'raison' => 'doublon'];
                    continue;
                }
                $finalitesDejaVues[$cle] = true;

                $t = DB::transaction(function () use ($client, $user, $q, $finalite) {
                    $t = \App\Models\Traitement::create([
                        'client_id' => $client->id,
                        'reference' => \App\Models\Traitement::genererReference($client->id),
                        'designation' => $finalite,
                        'description' => "Traitement généré automatiquement à partir du formulaire « {$q->titre} ».",
                        'direction_pole' => $q->pole,
                        'services_charges' => $q->service ? [$q->service] : [],
                        'sources' => ['Formulaire ARTCI : ' . $q->titre],
                        'contient_donnees_sensibles' => false,
                        'transfert_hors_cedeao' => false,
                        'statut' => 'brouillon',
                        'saisi_par' => $user->id,
                        'date_creation_fiche' => now()->toDateString(),
                        // Egale a la date de creation a l'init, ecrasee ensuite
                        // a chaque update. Garantit que la fiche MOBISOFT
                        // affiche bien une date de mise a jour des le depart.
                        'date_maj_fiche' => now()->toDateString(),
                    ]);

                    return $t;
                });
                $crees[] = ['id' => $t->id, 'designation' => $t->designation, 'pole' => $t->direction_pole];
            }
        }

        $this->audit->log('traitement.cree_depuis_questionnaires', [
            'client_id' => $client->id,
            'nb_crees' => count($crees),
            'nb_sautes' => count($sautes),
        ]);

        return response()->json([
            'nb_crees' => count($crees),
            'nb_sautes' => count($sautes),
            'crees' => $crees,
            'sautes' => $sautes,
            'message' => count($crees) > 0
                ? count($crees) . ' traitement(s) créé(s) en mode brouillon.'
                : 'Aucun nouveau traitement à créer (toutes les finalités existent déjà ou aucune réponse trouvée).',
        ], 201);
    }

    /**
     * Isole la reponse a la question "finalites" d'un questionnaire et la
     * decoupe en finalites individuelles (1 par ligne).
     */
    private function extraireFinalitesDuQuestionnaire(\App\Models\QuestionnaireGenere $q): array
    {
        $reponses = collect($q->reponses ?? [])->keyBy(fn ($r) => (int) ($r['numero'] ?? 0));
        foreach (($q->questions ?? []) as $question) {
            if (! $this->estQuestionFinalites($question['texte'] ?? '')) continue;
            $numero = (int) ($question['numero'] ?? 0);
            $rep = $reponses->get($numero);
            $texte = trim((string) ($rep['reponse'] ?? ''));
            if ($texte === '') continue;
            $finalites = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $texte)), fn ($l) => $l !== ''));

            return $finalites;
        }

        return [];
    }

    /**
     * Detection tolerante du texte type "finalites de la collecte / du traitement".
     */
    private function estQuestionFinalites(string $texte): bool
    {
        $t = mb_strtolower($texte);
        // Normalisation accents (NFD + suppression marques)
        if (function_exists('normalizer_normalize')) {
            $t = preg_replace('/[\x{0300}-\x{036f}]/u', '', normalizer_normalize($t, \Normalizer::FORM_D));
        }

        return str_contains($t, 'finalit') && (str_contains($t, 'collecte') || str_contains($t, 'traitement') || str_contains($t, 'donnee'));
    }

    public function preremplir(Request $request): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('create-traitements')) {
            abort(403, 'Permission create-traitements requise.');
        }

        $user = $request->user();
        $clientId = $user->clients()->value('clients.id');
        if (! $clientId) {
            return response()->json([
                'message' => 'Aucune entreprise rattachee a votre compte.',
                'preremplissage' => null,
            ], 422);
        }

        $client = \App\Models\Client::with(['secteursActivite:id,nom', 'organisme'])->find($clientId);

        // Pole de l'utilisateur connecte (utile pour direction_pole)
        $polePivot = \DB::table('client_user')
            ->where('user_id', $user->id)
            ->where('client_id', $clientId)
            ->value('pole');

        // Numero de fiche suggere (reference auto-generee a la creation reelle).
        $annee = now()->year;
        $numero = \App\Models\Traitement::where('client_id', $clientId)
            ->whereYear('created_at', $annee)
            ->count() + 1;
        $referenceSuggere = sprintf('TRT-%d-%03d', $annee, $numero);

        $organisme = $client->organisme ?? null;

        $preremplissage = [
            // Champs maitres
            'client_id' => $client->id,
            'client' => [
                'id' => $client->id,
                'raison_sociale' => $client->raison_sociale,
                'sigle' => $client->sigle,
                'secteurs_activite' => $client->secteursActivite->pluck('nom')->all(),
            ],
            'reference_suggere' => $referenceSuggere,
            'direction_pole' => $polePivot,
            'services_charges' => $polePivot ? [$polePivot] : [],
            'date_creation_fiche' => now()->toDateString(),
            'date_maj_fiche' => now()->toDateString(),
            'statut' => 'brouillon',
            // Suggestions a partir des infos organisme (RT/DPO).
            'organisme' => $organisme ? [
                'rt_nom' => $organisme->rt_nom,
                'rt_fonction' => $organisme->rt_fonction,
                'rt_email' => $organisme->rt_email,
                'dpo_nom' => $organisme->dpo_nom,
                'dpo_email' => $organisme->dpo_email,
            ] : null,
            // Saisi par l'utilisateur connecte
            'saisi_par' => [
                'id' => $user->id,
                'nom' => $user->nom,
                'prenom' => $user->prenom,
                'email' => $user->email,
            ],
        ];

        return response()->json([
            'preremplissage' => $preremplissage,
        ]);
    }

    private function charger(Traitement $traitement): Traitement
    {
        return $traitement->load([
            'client:id,raison_sociale',
            'saisiPar:id,nom,prenom',
            'validePar:id,nom,prenom',
            'supports', 'actes', 'personnes', 'categoriesDonnees', 'transferts', 'mesuresSecurite',
        ]);
    }

    private function extraireChampsMaitres(array $data): array
    {
        return collect($data)->only([
            'code_finalite', 'designation', 'description', 'direction_pole',
            'services_charges', 'sources', 'contient_donnees_sensibles',
            'transfert_hors_cedeao', 'date_creation_fiche', 'date_maj_fiche',
        ])->all();
    }

    /**
     * Synchronise les 6 sous-tables (suppression complete + recreation).
     * Strategie naive mais sure : evite les complications de diff.
     */
    private function synchroniserSousTables(Traitement $t, array $data): void
    {
        if (array_key_exists('supports', $data)) {
            $t->supports()->delete();
            foreach ($data['supports'] as $s) {
                $t->supports()->create($s);
            }
        }
        if (array_key_exists('actes', $data)) {
            $t->actes()->delete();
            foreach ($data['actes'] as $a) {
                $t->actes()->create($a);
            }
        }
        if (array_key_exists('personnes', $data)) {
            $t->personnes()->delete();
            foreach ($data['personnes'] as $p) {
                $t->personnes()->create($p);
            }
        }
        if (array_key_exists('categoriesDonnees', $data)) {
            $t->categoriesDonnees()->delete();
            foreach ($data['categoriesDonnees'] as $c) {
                $t->categoriesDonnees()->create($c);
            }
            $t->update(['contient_donnees_sensibles' => collect($data['categoriesDonnees'])->where('est_sensible', true)->isNotEmpty()]);
        }
        if (array_key_exists('transferts', $data)) {
            $t->transferts()->delete();
            foreach ($data['transferts'] as $tr) {
                $t->transferts()->create($tr);
            }
            $t->update(['transfert_hors_cedeao' => ! empty($data['transferts'])]);
        }
        if (array_key_exists('mesuresSecurite', $data)) {
            $t->mesuresSecurite()->delete();
            foreach ($data['mesuresSecurite'] as $m) {
                $t->mesuresSecurite()->create($m);
            }
        }
    }

    private function validerDonnees(Request $request, bool $partiel = false): array
    {
        $r = fn (string $reg) => $partiel ? "sometimes|{$reg}" : $reg;

        return $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'code_finalite' => 'nullable|string|max:32',
            'designation' => $r('required|string|max:255'),
            'description' => 'nullable|string',
            'direction_pole' => 'nullable|string|max:255',
            'services_charges' => 'nullable|array',
            'services_charges.*' => 'string|max:255',
            'sources' => 'nullable|array',
            'sources.*' => 'string|max:255',

            'supports' => 'nullable|array',
            'supports.*.categorie' => 'required_with:supports|in:materiel,logiciel,papier,autre',
            'supports.*.type' => 'nullable|string|max:255',
            'supports.*.marque_version' => 'nullable|string|max:255',
            'supports.*.precision' => 'nullable|string',

            'actes' => 'nullable|array',
            'actes.*.acte' => 'required_with:actes|string|max:255',
            'actes.*.base_legale' => 'required_with:actes|string|max:255',
            'actes.*.precision' => 'nullable|string',

            'personnes' => 'nullable|array',
            'personnes.*.categorie' => 'required_with:personnes|string|max:255',
            'personnes.*.documentation_source' => 'nullable|string',

            'categoriesDonnees' => 'nullable|array',
            'categoriesDonnees.*.categorie_principale' => 'required_with:categoriesDonnees|string|max:255',
            'categoriesDonnees.*.detail' => 'required_with:categoriesDonnees|string|max:255',
            'categoriesDonnees.*.origine' => 'required_with:categoriesDonnees|in:direct,indirect',
            'categoriesDonnees.*.est_sensible' => 'nullable|boolean',

            'transferts' => 'nullable|array',
            'transferts.*.organe' => 'required_with:transferts|string|max:255',
            'transferts.*.pays' => 'required_with:transferts|string|max:100',
            'transferts.*.garantie' => 'nullable|string',
            'transferts.*.sens_groupe' => 'nullable|string|max:100',

            'mesuresSecurite' => 'nullable|array',
            'mesuresSecurite.*.categorie' => 'required_with:mesuresSecurite|in:controle_acces,tracabilite,protection_logiciels,sauvegarde,chiffrement,controle_sous_traitants,autres',
            'mesuresSecurite.*.description' => 'required_with:mesuresSecurite|string',
        ]);
    }
}
