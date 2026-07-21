<?php

/**
 * QuestionnaireGenereController — API des questionnaires (formulaires)
 * lies a une mission. Sources : IA (Methode 2) ou modele saisi par
 * un agent AS Consulting (Methode 1).
 *
 * GET    /missions/{mission}/questionnaires       : liste les questionnaires
 * POST   /missions/{mission}/questionnaires       : cree un questionnaire (Methode 1)
 * GET    /questionnaires-generes/{q}              : detail
 * PUT    /questionnaires-generes/{q}              : edition (titre, description, questions, statut)
 * PUT    /questionnaires-generes/{q}/reponses     : enregistre les reponses
 * DELETE /questionnaires-generes/{q}              : suppression
 * POST   /questionnaires-generes/{q}/regenerer    : relance la generation IA pour ce pole
 *
 * Espace client :
 * GET    /client/questionnaires                   : liste les formulaires des missions du client connecte
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RegenererQuestionnaireJob;
use App\Mail\QuestionnairePublieMail;
use App\Models\Mission;
use App\Models\QuestionnaireGenere;
use App\Models\User;
use App\Services\Methode2\GenerateurQuestionnaireIA;
use App\Services\Methode3\AuditFlashTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

#[OA\PathItem(
    path: "/api/missions/{mission_id}/questionnaires",
    get: new OA\Get(
        operationId: "questionnaires-index-par-mission",
        summary: "Lister les questionnaires d'une mission",
        description: "Retourne la liste des questionnaires associés à une mission",
        tags: ["Questionnaires"],
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
                description: "Liste des questionnaires",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/QuestionnaireGenere")
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 404, description: "Mission non trouvée")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/questionnaires-generes/{id}",
    get: new OA\Get(
        operationId: "questionnaires-show",
        summary: "Afficher un questionnaire",
        description: "Retourne les détails complets d'un questionnaire avec questions et réponses",
        tags: ["Questionnaires"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID du questionnaire",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Détails du questionnaire",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "questionnaire", ref: "#/components/schemas/QuestionnaireGenere")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 404, description: "Questionnaire non trouvé")
        ]
    ),
    put: new OA\Put(
        operationId: "questionnaires-update",
        summary: "Mettre à jour un questionnaire",
        description: "Met à jour les informations et questions d'un questionnaire",
        tags: ["Questionnaires"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID du questionnaire",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "titre", type: "string", maxLength: 255, nullable: true, example: "Formulaire RGPD mis à jour"),
                    new OA\Property(property: "description", type: "string", nullable: true, example: "Description mise à jour"),
                    new OA\Property(property: "questions", type: "array", items: new OA\Items(
                        type: "object",
                        properties: [
                            new OA\Property(property: "numero", type: "integer", example: 1),
                            new OA\Property(property: "texte", type: "string", maxLength: 1000, example: "Question mise à jour"),
                            new OA\Property(property: "type", type: "string", enum: ["ouverte", "liste", "oui_non"], nullable: true),
                            new OA\Property(property: "themes", type: "array", items: new OA\Items(type: "string"), nullable: true),
                            new OA\Property(property: "options", type: "array", items: new OA\Items(type: "string"), nullable: true)
                        ]
                    )),
                    new OA\Property(property: "statut", type: "string", enum: ["brouillon", "envoye", "rempli", "valide"], nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Questionnaire mis à jour avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "questionnaire", ref: "#/components/schemas/QuestionnaireGenere")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 404, description: "Questionnaire non trouvé"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    ),
    delete: new OA\Delete(
        operationId: "questionnaires-destroy",
        summary: "Supprimer un questionnaire",
        description: "Supprime un questionnaire de manière irréversible",
        tags: ["Questionnaires"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID du questionnaire",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Questionnaire supprimé avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Questionnaire supprimé.")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 404, description: "Questionnaire non trouvé")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/questionnaires-generes/{id}/reponses",
    put: new OA\Put(
        operationId: "questionnaires-enregistrer-reponses",
        summary: "Enregistrer les réponses",
        description: "Enregistre les réponses d'un questionnaire et peut le finaliser",
        tags: ["Questionnaires"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID du questionnaire",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["reponses"],
                properties: [
                    new OA\Property(property: "reponses", type: "array", items: new OA\Items(
                        type: "object",
                        properties: [
                            new OA\Property(property: "numero", type: "integer", example: 1),
                            new OA\Property(property: "reponse", type: "string", nullable: true, example: "Données clients, employés")
                        ]
                    )),
                    new OA\Property(property: "finalise", type: "boolean", nullable: true, example: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Réponses enregistrées avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Réponses enregistrées."),
                        new OA\Property(property: "questionnaire", ref: "#/components/schemas/QuestionnaireGenere")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 404, description: "Questionnaire non trouvé"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    )
)]

class QuestionnaireGenereController extends Controller
{
    public function __construct(
        private GenerateurQuestionnaireIA $generateur,
    ) {}

    public function indexParMission(Mission $mission): JsonResponse
    {
        $questionnaires = QuestionnaireGenere::where('mission_id', $mission->id)
            ->with(['genereur:id,nom,prenom', 'repondeur:id,nom,prenom'])
            ->orderBy('pole')
            ->orderBy('service')
            ->get();

        return response()->json(['data' => $questionnaires]);
    }

    public function show(QuestionnaireGenere $questionnaire): JsonResponse
    {
        $questionnaire->load([
            'genereur:id,nom,prenom',
            'repondeur:id,nom,prenom',
            'mission:id,reference,titre,client_id',
            'mission.client:id,raison_sociale',
            'client:id,raison_sociale',
        ]);

        return response()->json(['questionnaire' => $questionnaire]);
    }

    public function update(Request $request, QuestionnaireGenere $questionnaire): JsonResponse
    {
        $data = $request->validate([
            'titre' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'questions' => 'nullable|array',
            'questions.*.numero' => 'required_with:questions|integer',
            'questions.*.texte' => 'required_with:questions|string|max:1000',
            'questions.*.type' => 'nullable|in:ouverte,liste,oui_non',
            'questions.*.themes' => 'nullable|array',
            'questions.*.options' => 'nullable|array',
            // Champs metiers preserves pour la Methode 3 (Audit Flash)
            'questions.*.domaine' => 'nullable|string|max:255',
            'questions.*.enjeu' => 'nullable|string|max:1000',
            'questions.*.source_legale' => 'nullable|string|max:255',
            'statut' => 'nullable|in:brouillon,envoye,rempli,valide',
        ]);

        if (($data['statut'] ?? null) === 'envoye' && ! $questionnaire->envoye_a) {
            $data['envoye_a'] = now();
        }

        $questionnaire->update($data);

        return response()->json(['questionnaire' => $questionnaire->fresh()]);
    }

    public function enregistrerReponses(Request $request, QuestionnaireGenere $questionnaire): JsonResponse
    {
        $data = $request->validate([
            'reponses' => 'required|array',
            'reponses.*.numero' => 'required|integer',
            'reponses.*.reponse' => 'nullable|string',
            'finalise' => 'nullable|boolean',
        ]);

        $reponsesNormalisees = collect($data['reponses'])->map(fn ($r) => [
            'numero' => (int) $r['numero'],
            'reponse' => $r['reponse'] ?? '',
            'repondu' => ! empty(trim((string) ($r['reponse'] ?? ''))),
        ])->all();

        $patch = [
            'reponses' => $reponsesNormalisees,
            'rempli_par' => $request->user()->id,
            'rempli_a' => now(),
        ];
        if ($data['finalise'] ?? false) {
            $patch['statut'] = 'rempli';
        }

        $questionnaire->update($patch);

        return response()->json(['questionnaire' => $questionnaire->fresh()]);
    }

    /**
     * Lance une regeneration IA ciblee sur CE questionnaire (re-prompt LLM
     * pour son pole/service uniquement, remplacement des questions sur place).
     *
     * Refuse si le questionnaire est deja publie ou si un job est deja en cours.
     * Asynchrone : retourne 202 et le front polling l'etat via /progress.
     */
    public function regenerer(Request $request, QuestionnaireGenere $questionnaire): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('regenerate-questionnaires')) {
            abort(403, 'Permission regenerate-questionnaires requise.');
        }
        if ($questionnaire->est_publie) {
            return response()->json([
                'message' => 'Ce questionnaire est deja publie chez le client. Depubliez-le d\'abord pour pouvoir le regenerer.',
            ], 422);
        }
        if (! $questionnaire->organigramme_id && ! $questionnaire->pole) {
            return response()->json(['message' => 'Aucun pole/service identifie : impossible de regenerer ce questionnaire.'], 422);
        }

        $cle = RegenererQuestionnaireJob::cleEtat($questionnaire->id);
        $etatCourant = Cache::get($cle);
        if ($etatCourant && in_array($etatCourant['etat'] ?? null, ['en_file', 'en_cours'], true)) {
            return response()->json([
                'message' => 'Une regeneration est deja en cours pour ce questionnaire.',
                'progress' => $etatCourant,
            ], 409);
        }

        Cache::put($cle, ['etat' => 'en_file', 'enqueue_at' => now()->toIso8601String()], now()->addHour());
        RegenererQuestionnaireJob::dispatch($questionnaire->id);

        return response()->json([
            'message' => 'Regeneration demarree en arriere-plan. Suivez l\'etat via /regenerer/progress.',
            'progress' => Cache::get($cle),
        ], 202);
    }

    /**
     * Regenere TOUS les questionnaires non-publies d'une mission (batch).
     * Les questionnaires publies sont laisses intacts. Dispatch un job par
     * questionnaire eligible, le front suit la progression individuelle.
     */
    public function regenererTous(Request $request, Mission $mission): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('regenerate-questionnaires')) {
            abort(403, 'Permission regenerate-questionnaires requise.');
        }

        $qs = QuestionnaireGenere::where('mission_id', $mission->id)->get();
        $dispatches = 0;
        $sautesPublies = 0;
        $sautesEnCours = 0;

        foreach ($qs as $q) {
            if ($q->est_publie) { $sautesPublies++; continue; }

            $cle = RegenererQuestionnaireJob::cleEtat($q->id);
            $etat = Cache::get($cle);
            if ($etat && in_array($etat['etat'] ?? null, ['en_file', 'en_cours'], true)) {
                $sautesEnCours++;
                continue;
            }

            Cache::put($cle, ['etat' => 'en_file', 'enqueue_at' => now()->toIso8601String()], now()->addHour());
            RegenererQuestionnaireJob::dispatch($q->id);
            $dispatches++;
        }

        return response()->json([
            'message' => "{$dispatches} questionnaire(s) en regeneration."
                . ($sautesPublies > 0 ? " {$sautesPublies} publie(s) ignores." : '')
                . ($sautesEnCours > 0 ? " {$sautesEnCours} deja en cours." : ''),
            'dispatches' => $dispatches,
            'sautes_publies' => $sautesPublies,
            'sautes_en_cours' => $sautesEnCours,
            'total' => $qs->count(),
        ], 202);
    }

    /**
     * Publie en lot tous les questionnaires d'une mission qui contiennent au
     * moins une question. Envoie les emails de notification pour chacun.
     */
    public function publierTous(Request $request, Mission $mission): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('view-all-questionnaires')) {
            abort(403, 'Permission view-all-questionnaires requise.');
        }

        $qs = QuestionnaireGenere::where('mission_id', $mission->id)->get();
        $publies = 0;
        $sautesVides = 0;
        $sautesDeja = 0;
        $emailsEnvoyes = 0;

        foreach ($qs as $q) {
            if ($q->est_publie) { $sautesDeja++; continue; }
            $questions = $q->questions ?? [];
            if (empty($questions)) { $sautesVides++; continue; }

            $q->update([
                'est_publie' => true,
                'publie_le' => now(),
                'publie_par' => $request->user()->id,
                'statut' => $q->statut === 'brouillon' ? 'envoye' : $q->statut,
                'envoye_a' => $q->envoye_a ?? now(),
            ]);
            $publies++;

            $destinataires = $this->destinatairesPourQuestionnaire($q);
            foreach ($destinataires as $dest) {
                try {
                    Mail::to($dest->email)->send(new QuestionnairePublieMail(
                        questionnaire: $q,
                        destinataire: $dest,
                        nomEntreprise: $q->mission?->client?->raison_sociale,
                        publiePar: trim(($request->user()->prenom ?? '') . ' ' . ($request->user()->nom ?? '')) ?: null,
                    ));
                    $emailsEnvoyes++;
                } catch (\Throwable $e) {
                    Log::warning("QuestionnairePublieMail (batch) : echec envoi a {$dest->email}", ['error' => $e->getMessage()]);
                }
            }
        }

        return response()->json([
            'message' => "{$publies} questionnaire(s) publie(s)."
                . ($sautesDeja > 0 ? " {$sautesDeja} deja publie(s) ignores." : '')
                . ($sautesVides > 0 ? " {$sautesVides} sans question ignores." : '')
                . ($emailsEnvoyes > 0 ? " {$emailsEnvoyes} e-mail(s) envoye(s)." : ''),
            'publies' => $publies,
            'sautes_deja' => $sautesDeja,
            'sautes_vides' => $sautesVides,
            'emails_envoyes' => $emailsEnvoyes,
            'total' => $qs->count(),
        ]);
    }

    /**
     * Depublie en lot tous les questionnaires publies d'une mission.
     */
    public function depublierTous(Request $request, Mission $mission): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('view-all-questionnaires')) {
            abort(403, 'Permission view-all-questionnaires requise.');
        }

        $depublies = QuestionnaireGenere::where('mission_id', $mission->id)
            ->where('est_publie', true)
            ->update([
                'est_publie' => false,
                'publie_le' => null,
                'publie_par' => null,
            ]);

        return response()->json([
            'message' => "{$depublies} questionnaire(s) depublie(s).",
            'depublies' => $depublies,
        ]);
    }

    /**
     * Etat de la regeneration (polling depuis le front).
     */
    public function regenererProgress(Request $request, QuestionnaireGenere $questionnaire): JsonResponse
    {
        if (! $request->user()->hasAnyPermission(['regenerate-questionnaires', 'view-questionnaires'])) {
            abort(403);
        }
        $cle = RegenererQuestionnaireJob::cleEtat($questionnaire->id);

        return response()->json([
            'progress' => Cache::get($cle),
            'questionnaire' => [
                'id' => $questionnaire->id,
                'source' => $questionnaire->source,
                'nb_questions' => count($questionnaire->questions ?? []),
                'est_publie' => (bool) $questionnaire->est_publie,
            ],
        ]);
    }

    /**
     * Cree un questionnaire vierge attache a la mission. Utilise pour
     * la Methode 1 (formulaire concu par l'agent AS Consulting) ou
     * pour ajouter un formulaire ad hoc a une mission de Methode 2.
     */
    public function store(Request $request, Mission $mission): JsonResponse
    {
        $data = $request->validate([
            'titre' => 'required|string|max:255',
            'description' => 'nullable|string',
            'pole' => 'nullable|string|max:255',
            'service' => 'nullable|string|max:255',
            'questions' => 'nullable|array',
            'questions.*.numero' => 'required_with:questions|integer',
            'questions.*.texte' => 'required_with:questions|string|max:1000',
            'questions.*.type' => 'nullable|in:ouverte,liste,oui_non',
            'questions.*.themes' => 'nullable|array',
            'questions.*.options' => 'nullable|array',
            'questions.*.domaine' => 'nullable|string|max:255',
            'questions.*.enjeu' => 'nullable|string|max:1000',
            'questions.*.source_legale' => 'nullable|string|max:255',
            'themes' => 'nullable|array',
        ]);

        $questionnaire = QuestionnaireGenere::create([
            'mission_id' => $mission->id,
            'pole' => $data['pole'] ?? 'Formulaire mission',
            'service' => $data['service'] ?? null,
            'titre' => $data['titre'],
            'description' => $data['description'] ?? null,
            'questions' => $data['questions'] ?? [],
            'source' => 'manuel',
            'themes' => $data['themes'] ?? [],
            'statut' => 'brouillon',
            'genere_par' => $request->user()->id,
        ]);

        return response()->json(['questionnaire' => $questionnaire], 201);
    }

    public function destroy(Request $request, QuestionnaireGenere $questionnaire): JsonResponse
    {
        // Suppression reservee aux roles ASC (consultant/manager/admin).
        // Les client/client_admin ont uniquement view-client-questionnaires.
        if (! $request->user()->hasPermissionTo('view-all-questionnaires')) {
            abort(403, 'La suppression d\'un formulaire est reservee a AS Consulting.');
        }
        $this->verifierAcces($request->user(), $questionnaire);
        if ($questionnaire->est_publie) {
            abort(422, 'Ce questionnaire est publie : depubliez-le d\'abord pour pouvoir le supprimer.');
        }
        $questionnaire->delete();

        return response()->json(['message' => 'Formulaire supprime.']);
    }

    /**
     * Liste les formulaires accessibles au client connecte (toutes ses missions).
     */
    public function indexClient(Request $request): JsonResponse
    {
        $user = $request->user();
        $clientIds = $user->clients()->pluck('clients.id');

        if ($clientIds->isEmpty()) {
            return response()->json(['data' => []]);
        }

        // Cote client : on ne montre que les questionnaires publies par ASC.
        $query = QuestionnaireGenere::query()
            ->publies()
            ->whereHas('mission', fn ($q) => $q->whereIn('client_id', $clientIds))
            ->with(['mission:id,reference,titre,client_id,methode', 'mission.client:id,raison_sociale']);

        // Pour les users standards (PAS client_admin), on filtre selon le
        // rattachement pivot :
        //   - service NULL : visibilite sur TOUS les questionnaires du pole
        //   - service rempli : visibilite limitee a (pole, service) precis
        if (! $user->hasPermissionTo('manage-client-users')) {
            $pivot = \DB::table('client_user')
                ->where('user_id', $user->id)
                ->whereIn('client_id', $clientIds)
                ->select('pole', 'service')
                ->first();

            if (! $pivot || ! $pivot->pole) {
                return response()->json(['data' => []]);
            }

            $query->where('pole', $pivot->pole);
            if (! empty($pivot->service)) {
                $query->where('service', $pivot->service);
            }
        }

        $questionnaires = $query->orderByDesc('created_at')->get();

        return response()->json(['data' => $questionnaires]);
    }

    /**
     * Resultat d'un questionnaire Audit Flash (Methode 3).
     * Calcule le score (Oui = 0 ; Non/Je ne sais pas = +10) et restitue
     * la zone de risque + le detail des alertes par domaine.
     *
     * Accessible par le client (sur ses missions) et par les agents AS Consulting.
     */
    public function auditFlashResultat(Request $request, QuestionnaireGenere $questionnaire): JsonResponse
    {
        $this->verifierAcces($request->user(), $questionnaire);

        $questionnaire->load([
            'mission:id,reference,titre,client_id,methode',
            'mission.client:id,raison_sociale',
            'repondeur:id,nom,prenom',
        ]);

        $reponsesParNumero = collect($questionnaire->reponses ?? [])
            ->keyBy(fn ($r) => (int) ($r['numero'] ?? 0));

        $details = [];
        $scoreTotal = 0;
        $alertes = [];
        $repondues = 0;

        foreach (($questionnaire->questions ?? []) as $q) {
            $numero = (int) ($q['numero'] ?? 0);
            $reponse = (string) ($reponsesParNumero[$numero]['reponse'] ?? '');
            $reponseNorm = trim(mb_strtolower($reponse));
            if ($reponseNorm !== '') {
                $repondues++;
            }
            $score = AuditFlashTemplate::scoreReponse($reponse);
            $scoreTotal += $score;

            $statut = match ($reponseNorm) {
                'oui' => 'conforme',
                'non' => 'alerte',
                'je ne sais pas' => 'a_verifier',
                default => 'sans_reponse',
            };

            $details[] = [
                'numero' => $numero,
                'domaine' => $q['domaine'] ?? null,
                'texte' => $q['texte'] ?? '',
                'enjeu' => $q['enjeu'] ?? null,
                'source_legale' => $q['source_legale'] ?? null,
                'reponse' => $reponse,
                'score' => $score,
                'statut' => $statut,
            ];

            if (in_array($statut, ['alerte', 'a_verifier'], true)) {
                $alertes[] = [
                    'numero' => $numero,
                    'domaine' => $q['domaine'] ?? null,
                    'enjeu' => $q['enjeu'] ?? null,
                    'source_legale' => $q['source_legale'] ?? null,
                    'statut' => $statut,
                ];
            }
        }

        $total = count($questionnaire->questions ?? []);
        if ($scoreTotal <= 10) {
            $zone = 'conforme';
            $zoneLabel = 'Conforme';
            $zoneMessage = 'Statistiquement rare sans accompagnement specialise. ASC recommande un audit de verification des preuves.';
        } elseif ($scoreTotal <= 40) {
            $zone = 'danger';
            $zoneLabel = 'Zone de danger';
            $zoneMessage = 'Vulnerabilite critique. Amende administrative imminente. ASC recommande un plan d\'action sous 30 jours.';
        } else {
            $zone = 'rouge';
            $zoneLabel = 'Zone rouge sang (urgence absolue)';
            $zoneMessage = 'Situation d\'infraction penale continue. Risque de prison et de fermeture administrative. Action requise : deploiement immediat du Bouclier ASC.';
        }

        return response()->json([
            'questionnaire' => [
                'id' => $questionnaire->id,
                'titre' => $questionnaire->titre,
                'statut' => $questionnaire->statut,
                'mission' => $questionnaire->mission?->only(['id', 'reference', 'titre', 'methode']),
                'client' => $questionnaire->mission?->client?->only(['id', 'raison_sociale']),
                'rempli_par' => $questionnaire->repondeur?->only(['id', 'nom', 'prenom']),
                'rempli_a' => $questionnaire->rempli_a,
            ],
            'resultat' => [
                'score_total' => $scoreTotal,
                'score_max' => $total * 10,
                'total_questions' => $total,
                'repondues' => $repondues,
                'zone' => $zone,
                'zone_label' => $zoneLabel,
                'zone_message' => $zoneMessage,
                'alertes_count' => count($alertes),
                'alertes' => $alertes,
                'details' => $details,
            ],
        ]);
    }

    /**
     * Verifie qu'un utilisateur peut acceder au questionnaire.
     *
     * Regles :
     *   - Internes (view-questionnaires/view-all-questionnaires) : acces total
     *   - Cote client : doit appartenir a l'entreprise ET le questionnaire doit
     *     etre publie (est_publie=true)
     *   - Cote client standard (sans manage-client-users) : doit en plus avoir
     *     le meme pole que le questionnaire
     */
    private function verifierAcces($user, QuestionnaireGenere $questionnaire): void
    {
        if (! $user || $user->hasAnyPermission(['view-questionnaires', 'view-all-questionnaires'])) {
            return;
        }
        $clientIds = $user->clients()->pluck('clients.id');
        // Audit Flash libre : le questionnaire est rattache directement au client
        // (client_id) sans mission_id. On verifie cette piste avant celle de la mission.
        $clientDirect = $questionnaire->client_id;
        $clientMission = $questionnaire->mission?->client_id;
        $clientAttendu = $clientDirect ?: $clientMission;
        if (! $clientAttendu || ! $clientIds->contains($clientAttendu)) {
            abort(403, 'Acces refuse : ce formulaire ne fait pas partie de vos formulaires.');
        }

        // Le questionnaire doit etre publie pour etre accessible cote client.
        if (! $questionnaire->est_publie) {
            abort(403, 'Ce formulaire n\'est pas encore publie.');
        }

        // Si l'utilisateur n'est pas client_admin (pas de permission manage-client-users),
        // il ne peut voir que le questionnaire correspondant a son pole.
        if (! $user->hasPermissionTo('manage-client-users')) {
            $pivot = \DB::table('client_user')
                ->where('user_id', $user->id)
                ->where('client_id', $clientAttendu)
                ->select('pole', 'service')
                ->first();

            // Pas de rattachement OU pole different OU service different (si scope)
            $poleMatch = $pivot && $questionnaire->pole && $pivot->pole === $questionnaire->pole;
            $serviceMatch = empty($pivot?->service) || $pivot->service === $questionnaire->service;
            if (! $poleMatch || ! $serviceMatch) {
                abort(403, 'Acces refuse : ce formulaire ne correspond pas a votre rattachement (pole/service).');
            }
        }
    }

    // ============================================================
    // PHASE 4 — Workflow de publication par ASC
    // ============================================================

    /**
     * Publie un questionnaire : le rend visible aux utilisateurs cote client.
     * Reserve aux utilisateurs avec la permission view-all-questionnaires.
     */
    public function publier(Request $request, QuestionnaireGenere $questionnaire): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('view-all-questionnaires')) {
            abort(403, 'Permission view-all-questionnaires requise.');
        }

        if ($questionnaire->est_publie) {
            return response()->json(['message' => 'Ce questionnaire est deja publie.'], 422);
        }

        $questions = $questionnaire->questions ?? [];
        if (empty($questions)) {
            return response()->json([
                'message' => 'Impossible de publier : le questionnaire ne contient aucune question.',
            ], 422);
        }

        $questionnaire->update([
            'est_publie' => true,
            'publie_le' => now(),
            'publie_par' => $request->user()->id,
            'statut' => $questionnaire->statut === 'brouillon' ? 'envoye' : $questionnaire->statut,
            'envoye_a' => $questionnaire->envoye_a ?? now(),
        ]);

        $destinataires = $this->destinatairesPourQuestionnaire($questionnaire);
        $emailsEnvoyes = 0;
        foreach ($destinataires as $dest) {
            try {
                Mail::to($dest->email)->send(new QuestionnairePublieMail(
                    questionnaire: $questionnaire,
                    destinataire: $dest,
                    nomEntreprise: $questionnaire->mission?->client?->raison_sociale,
                    publiePar: trim(($request->user()->prenom ?? '') . ' ' . ($request->user()->nom ?? '')) ?: null,
                ));
                $emailsEnvoyes++;
            } catch (\Throwable $e) {
                Log::warning("QuestionnairePublieMail : echec envoi a {$dest->email}", ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'message' => 'Questionnaire publie. Il est desormais visible aux utilisateurs du pole concerne.'
                . ($emailsEnvoyes > 0 ? " {$emailsEnvoyes} e-mail(s) de notification envoye(s)." : ''),
            'questionnaire' => $questionnaire->fresh(),
            'emails_envoyes' => $emailsEnvoyes,
        ]);
    }

    /**
     * Resout la liste des destinataires d'un mail de publication.
     * Deux familles cumulables :
     *   1. Utilisateurs rattaches au POLE complet (pivot.pole = X, service NULL)
     *      — couvrent l'integralite des questionnaires du pole.
     *   2. Utilisateurs rattaches au SERVICE precis (pivot.pole = X, service = Y)
     *      — uniquement si le questionnaire publie correspond exactement.
     * Fallback : les client_admin si personne n'est rattache.
     */
    private function destinatairesPourQuestionnaire(QuestionnaireGenere $questionnaire): array
    {
        $clientId = $questionnaire->mission?->client_id;
        if (! $clientId || ! $questionnaire->pole) {
            return [];
        }

        $q = DB::table('client_user')
            ->where('client_id', $clientId)
            ->whereRaw('LOWER(pole) = LOWER(?)', [$questionnaire->pole])
            ->where(function ($w) use ($questionnaire) {
                // Cas 1 : responsable du pole entier (service null)
                $w->whereNull('service');
                // Cas 2 : responsable du service correspondant
                if (! empty($questionnaire->service)) {
                    $w->orWhereRaw('LOWER(service) = LOWER(?)', [$questionnaire->service]);
                }
            });

        $userIds = $q->pluck('user_id');

        $users = User::whereIn('id', $userIds)
            ->where('is_active', true)
            ->where('compte_valide', true)
            ->get();

        if ($users->isNotEmpty()) {
            return $users->all();
        }

        // Fallback : on prend les client_admin de l'entreprise
        $adminIds = DB::table('client_user')->where('client_id', $clientId)->pluck('user_id');

        return User::whereIn('id', $adminIds)
            ->whereHas('role', fn ($q) => $q->where('name', 'client_admin'))
            ->where('is_active', true)
            ->where('compte_valide', true)
            ->get()
            ->all();
    }

    /**
     * Depublie un questionnaire : le retire de la vue client.
     */
    public function depublier(Request $request, QuestionnaireGenere $questionnaire): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('view-all-questionnaires')) {
            abort(403, 'Permission view-all-questionnaires requise.');
        }

        $questionnaire->update([
            'est_publie' => false,
            'publie_le' => null,
            'publie_par' => null,
        ]);

        return response()->json([
            'message' => 'Questionnaire depublie.',
            'questionnaire' => $questionnaire->fresh(),
        ]);
    }

    /**
     * Ajoute une question a un questionnaire. Reserve a ASC.
     */
    public function ajouterQuestion(Request $request, QuestionnaireGenere $questionnaire): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('view-all-questionnaires')) {
            abort(403, 'Permission view-all-questionnaires requise.');
        }
        if ($questionnaire->est_publie) {
            abort(422, 'Ce questionnaire est publie : depubliez-le pour pouvoir ajouter des questions.');
        }

        $data = $request->validate([
            'texte' => 'required|string|max:1500',
            'type' => 'nullable|string|in:texte,liste,oui_non,nombre',
            'options' => 'nullable|array',
            'options.*' => 'string|max:255',
            'domaine' => 'nullable|string|max:100',
            'themes' => 'nullable|array',
            'themes.*' => 'string|max:100',
        ]);

        $questions = $questionnaire->questions ?? [];
        $nouveauNumero = empty($questions) ? 1 : (max(array_column($questions, 'numero')) + 1);

        $questions[] = [
            'numero' => $nouveauNumero,
            'texte' => $data['texte'],
            'type' => $data['type'] ?? 'texte',
            'options' => $data['options'] ?? [],
            'domaine' => $data['domaine'] ?? null,
            'themes' => $data['themes'] ?? [],
        ];

        $questionnaire->update(['questions' => $questions]);

        return response()->json([
            'message' => 'Question ajoutee.',
            'questionnaire' => $questionnaire->fresh(),
        ]);
    }

    /**
     * Modifie une question existante (par son numero).
     */
    public function modifierQuestion(Request $request, QuestionnaireGenere $questionnaire, int $numero): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('view-all-questionnaires')) {
            abort(403, 'Permission view-all-questionnaires requise.');
        }
        if ($questionnaire->est_publie) {
            abort(422, 'Ce questionnaire est publie : depubliez-le pour pouvoir modifier les questions.');
        }

        $data = $request->validate([
            'texte' => 'sometimes|string|max:1500',
            'type' => 'sometimes|string|in:texte,liste,oui_non,nombre',
            'options' => 'nullable|array',
            'options.*' => 'string|max:255',
            'domaine' => 'nullable|string|max:100',
            'themes' => 'nullable|array',
            'themes.*' => 'string|max:100',
        ]);

        $questions = $questionnaire->questions ?? [];
        $trouvee = false;

        foreach ($questions as &$q) {
            if ((int) ($q['numero'] ?? 0) === $numero) {
                $q = array_merge($q, $data);
                $trouvee = true;
                break;
            }
        }
        unset($q);

        if (! $trouvee) {
            return response()->json(['message' => "Question numero $numero introuvable."], 404);
        }

        $questionnaire->update(['questions' => $questions]);

        return response()->json([
            'message' => 'Question modifiee.',
            'questionnaire' => $questionnaire->fresh(),
        ]);
    }

    /**
     * Supprime une question (par son numero). Les numeros suivants ne sont
     * PAS reordonnes pour preserver les references existantes dans les reponses.
     */
    public function supprimerQuestion(Request $request, QuestionnaireGenere $questionnaire, int $numero): JsonResponse
    {
        if (! $request->user()->hasPermissionTo('view-all-questionnaires')) {
            abort(403, 'Permission view-all-questionnaires requise.');
        }
        if ($questionnaire->est_publie) {
            abort(422, 'Ce questionnaire est publie : depubliez-le pour pouvoir supprimer une question.');
        }

        $questions = $questionnaire->questions ?? [];
        $filtrees = array_values(array_filter($questions, fn ($q) => (int) ($q['numero'] ?? 0) !== $numero));

        if (count($filtrees) === count($questions)) {
            return response()->json(['message' => "Question numero $numero introuvable."], 404);
        }

        $questionnaire->update(['questions' => $filtrees]);

        return response()->json([
            'message' => 'Question supprimee.',
            'questionnaire' => $questionnaire->fresh(),
        ]);
    }

    /**
     * Export PDF d'un questionnaire (rempli ou vierge). Inclut les reponses
     * deja saisies + l'identite de la mission/entreprise.
     */
    public function exportPdf(Request $request, QuestionnaireGenere $questionnaire): Response
    {
        $user = $request->user();
        if (! $user->hasAnyPermission(['view-questionnaires', 'view-client-questionnaires'])) {
            abort(403, 'Permission de consultation requise.');
        }
        if ($user->hasPermissionTo('view-client-questionnaires') && ! $user->hasPermissionTo('view-questionnaires')) {
            // Cote client : on n'autorise l'export que pour les questionnaires publies de son entreprise.
            if (! $questionnaire->est_publie) {
                abort(403, 'Ce formulaire n\'est pas encore publie.');
            }
            $clientIds = $user->clients()->pluck('clients.id');
            if (! $clientIds->contains($questionnaire->mission?->client_id)) {
                abort(403, 'Ce formulaire ne fait pas partie de votre entreprise.');
            }
        }

        $questionnaire->load(['mission:id,reference,titre,client_id', 'mission.client:id,raison_sociale']);

        $pdf = Pdf::loadView('pdf.questionnaire', ['questionnaire' => $questionnaire])
            ->setPaper('a4', 'portrait');

        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '_', $questionnaire->titre ?? "questionnaire_{$questionnaire->id}");
        $filename = trim("{$slug}_q{$questionnaire->id}", '_') . '.pdf';

        return $pdf->download($filename);
    }
}
