<?php

/**
 * Controleur PlanActionController — Plans d'actions.
 *
 * CRUD + cycle de vie :
 *   propose -> accepte_client -> en_cours -> cloture
 *   (ou) propose -> rejete
 *
 * Items :
 *   - ASC ajoute des items lors de la creation/modification
 *   - Client met a jour le statut des items (a_faire -> en_cours -> termine)
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlanAction;
use App\Models\PlanActionItem;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Illuminate\Support\Facades\DB;

#[OA\PathItem(
    path: "/api/plans-action",
    get: new OA\Get(
        operationId: "plans-action-index",
        summary: "Lister les plans d'action",
        description: "Retourne la liste paginée des plans d'action avec filtres optionnels",
        tags: ["Plans d'Action"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "statut",
                in: "query",
                description: "Filtrer par statut (propose, accepte_client, en_cours, cloture, rejete)",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["propose", "accepte_client", "en_cours", "cloture", "rejete"])
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
                schema: new OA\Schema(type: "integer", default: 20)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des plans d'action",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/PlanAction")
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
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé")
        ]
    ),
    post: new OA\Post(
        operationId: "plans-action-store",
        summary: "Créer un plan d'action",
        description: "Crée un nouveau plan d'action pour corriger les écarts de conformité",
        tags: ["Plans d'Action"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["titre", "client_id"],
                properties: [
                    new OA\Property(property: "titre", type: "string", maxLength: 255, example: "Plan de mise en conformité RGPD"),
                    new OA\Property(property: "description", type: "string", nullable: true, example: "Plan d'action pour corriger les écarts identifiés"),
                    new OA\Property(property: "client_id", type: "integer", example: 1),
                    new OA\Property(property: "analyse_id", type: "integer", nullable: true, example: 1),
                    new OA\Property(property: "items", type: "array", items: new OA\Items(
                        type: "object",
                        properties: [
                            new OA\Property(property: "titre", type: "string", example: "Mettre en place un registre des activités"),
                            new OA\Property(property: "description", type: "string", nullable: true),
                            new OA\Property(property: "ecart_id", type: "integer", nullable: true, example: 1),
                            new OA\Property(property: "responsable_id", type: "integer", nullable: true, example: 2),
                            new OA\Property(property: "echeance", type: "string", format: "date", nullable: true, example: "2024-02-15"),
                            new OA\Property(property: "position", type: "integer", example: 1)
                        ]
                    ))
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Plan d'action créé avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "plan", ref: "#/components/schemas/PlanAction")
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
    path: "/api/plans-action/{id}",
    get: new OA\Get(
        operationId: "plans-action-show",
        summary: "Afficher un plan d'action",
        description: "Retourne les détails complets d'un plan d'action avec ses items et permissions",
        tags: ["Plans d'Action"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID du plan d'action",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Détails du plan d'action",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "plan", ref: "#/components/schemas/PlanAction"),
                        new OA\Property(property: "progression", type: "number", example: 65.5),
                        new OA\Property(property: "peut_modifier", type: "boolean", example: true),
                        new OA\Property(property: "peut_accepter", type: "boolean", example: false),
                        new OA\Property(property: "peut_cloturer", type: "boolean", example: false),
                        new OA\Property(property: "peut_mettre_a_jour_items", type: "boolean", example: true),
                        new OA\Property(property: "peut_supprimer", type: "boolean", example: false)
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé"),
            new OA\Response(response: 404, description: "Plan d'action non trouvé")
        ]
    )
)]

class PlanActionController extends Controller
{
    public function __construct(
        private AuditService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PlanAction::class);
        $user = $request->user();

        $query = PlanAction::query()
            ->with([
                'client:id,raison_sociale',
                'analyse:id,reference,titre',
                'proposeur:id,nom,prenom',
                'accepteur:id,nom,prenom',
            ])
            ->withCount('items');

        // User sans permission de gerer tous les plans : scoper a ses propres clients.
        if (! $user->hasPermissionTo('view-all-plans-actions')) {
            $clientIds = $user->clients()->pluck('clients.id');
            $query->whereIn('client_id', $clientIds);
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function show(Request $request, PlanAction $plan): JsonResponse
    {
        $this->authorize('view', $plan);

        $plan->load([
            // Colonne `secteur_activite` supprimee (migration 2026_05_12_210000) ;
            // les secteurs sont desormais relationnels via secteursActivite [{id,nom}].
            'client:id,raison_sociale',
            'client.secteursActivite:id,nom',
            'analyse:id,reference,titre,score_conformite',
            'proposeur:id,nom,prenom',
            'accepteur:id,nom,prenom',
            'soumetteur:id,nom,prenom',
            'items' => fn ($q) => $q->orderBy('position'),
            'items.ecart:id,titre,gravite,recommandation',
            'items.responsable:id,nom,prenom',
            'items.preuves' => fn ($q) => $q->latest(),
            'items.preuves.uploadeur:id,nom,prenom',
        ]);

        $user = $request->user();
        $estProposeurOuAsc = $user->hasAnyPermission(['view-all-plans-actions', 'update-plans-actions']);

        return response()->json([
            'plan' => $plan,
            'progression' => $plan->progression(),
            'peut_modifier' => $user->can('update', $plan),
            'peut_accepter' => $user->can('accepter', $plan),
            'peut_cloturer' => $user->can('cloturer', $plan),
            'peut_mettre_a_jour_items' => $user->can('mettreAJourItem', $plan),
            'peut_supprimer' => $user->can('delete', $plan),
            // Soumission au consultant : un user qui peut mettre a jour les items
            // (client_admin du client OU consultant), et le plan accepte mais
            // pas encore soumis ou avec verification terminee (re-soumission).
            'peut_soumettre' => $user->can('mettreAJourItem', $plan)
                && in_array($plan->statut, ['accepte_client', 'en_cours'], true)
                && (! $plan->soumis_le || $plan->verification_statut === 'terminee'),
            // Re-ouverture par le consultant (annule la soumission pour permettre
            // au client d'ajouter de nouvelles preuves apres revue).
            'peut_rouvrir' => $estProposeurOuAsc
                && $plan->soumis_le
                && $plan->verification_statut === 'terminee',
        ]);
    }

    /**
     * Creation du plan par un consultant ASC.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', PlanAction::class);

        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'analyse_id' => 'nullable|exists:analyses,id',
            'titre' => 'required|string|max:255',
            'objectif' => 'nullable|string',
            'date_debut_prevue' => 'nullable|date',
            'date_fin_prevue' => 'nullable|date|after_or_equal:date_debut_prevue',
            'items' => 'nullable|array',
            'items.*.titre' => 'required_with:items|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.priorite' => 'nullable|in:p1,p2,p3,p4',
            'items.*.echeance' => 'nullable|date',
            'items.*.ecart_id' => 'nullable|exists:ecarts,id',
        ]);

        $user = $request->user();

        $plan = DB::transaction(function () use ($data, $user) {
            $p = PlanAction::create([
                'client_id' => $data['client_id'],
                'analyse_id' => $data['analyse_id'] ?? null,
                'reference' => PlanAction::genererReference(),
                'titre' => $data['titre'],
                'objectif' => $data['objectif'] ?? null,
                'propose_par' => $user->id,
                'statut' => 'propose',
                'date_debut_prevue' => $data['date_debut_prevue'] ?? null,
                'date_fin_prevue' => $data['date_fin_prevue'] ?? null,
            ]);

            foreach ($data['items'] ?? [] as $i => $item) {
                PlanActionItem::create([
                    'plan_action_id' => $p->id,
                    'position' => $i,
                    'titre' => $item['titre'],
                    'description' => $item['description'] ?? null,
                    'priorite' => $item['priorite'] ?? 'p2',
                    'statut' => 'a_faire',
                    'echeance' => $item['echeance'] ?? null,
                    'ecart_id' => $item['ecart_id'] ?? null,
                ]);
            }

            return $p;
        });

        $this->audit->log('plan_action.cree', [
            'plan_id' => $plan->id,
            'client_id' => $plan->client_id,
            'nb_items' => count($data['items'] ?? []),
        ]);

        return response()->json([
            'plan' => $plan->load('client:id,raison_sociale', 'proposeur:id,nom,prenom', 'items'),
            'message' => 'Plan d\'action cree et propose au client.',
        ], 201);
    }

    /**
     * Modifie les metadonnees du plan (ASC uniquement).
     */
    public function update(Request $request, PlanAction $plan): JsonResponse
    {
        $this->authorize('update', $plan);

        $data = $request->validate([
            'titre' => 'sometimes|string|max:255',
            'objectif' => 'nullable|string',
            'date_debut_prevue' => 'nullable|date',
            'date_fin_prevue' => 'nullable|date',
            'statut' => 'sometimes|in:propose,accepte_client,en_cours,cloture,rejete',
        ]);

        $plan->update($data);

        $this->audit->log('plan_action.modifie', ['plan_id' => $plan->id]);

        return response()->json(['plan' => $plan->fresh()]);
    }

    /**
     * Le client_admin accepte le plan propose.
     */
    public function accepter(Request $request, PlanAction $plan): JsonResponse
    {
        $this->authorize('accepter', $plan);

        $plan->update([
            'statut' => 'accepte_client',
            'accepte_le' => now(),
            'accepte_par' => $request->user()->id,
        ]);

        $this->audit->log('plan_action.accepte', ['plan_id' => $plan->id]);

        return response()->json([
            'plan' => $plan->fresh()->load('accepteur:id,nom,prenom'),
            'message' => 'Plan d\'action accepte. Vous pouvez maintenant mettre a jour les actions.',
        ]);
    }

    /**
     * Le consultant cloture le plan.
     */
    public function cloturer(Request $request, PlanAction $plan): JsonResponse
    {
        $this->authorize('cloturer', $plan);

        $data = $request->validate([
            'commentaire' => 'nullable|string|max:1000',
        ]);

        $plan->update([
            'statut' => 'cloture',
            'cloture_le' => now(),
            'commentaire_cloture' => $data['commentaire'] ?? null,
        ]);

        $this->audit->log('plan_action.cloture', ['plan_id' => $plan->id]);

        return response()->json([
            'plan' => $plan->fresh(),
            'message' => 'Plan d\'action cloture.',
        ]);
    }

    public function destroy(Request $request, PlanAction $plan): JsonResponse
    {
        $this->authorize('delete', $plan);
        $plan->delete();

        return response()->json(['message' => 'Plan d\'action supprime.']);
    }

    // ============================================================
    // ITEMS
    // ============================================================

    public function ajouterItem(Request $request, PlanAction $plan): JsonResponse
    {
        $this->authorize('update', $plan);

        $data = $request->validate([
            'titre' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priorite' => 'nullable|in:p1,p2,p3,p4',
            'echeance' => 'nullable|date',
            'ecart_id' => 'nullable|exists:ecarts,id',
            'responsable_id' => 'nullable|exists:users,id',
        ]);

        $position = $plan->items()->max('position') + 1;

        $item = PlanActionItem::create(array_merge($data, [
            'plan_action_id' => $plan->id,
            'position' => $position,
            'statut' => 'a_faire',
            'priorite' => $data['priorite'] ?? 'p2',
        ]));

        return response()->json(['item' => $item, 'message' => 'Action ajoutee.'], 201);
    }

    public function mettreAJourItem(Request $request, PlanAction $plan, PlanActionItem $item): JsonResponse
    {
        $this->authorize('mettreAJourItem', $plan);
        abort_unless($item->plan_action_id === $plan->id, 404);

        $user = $request->user();

        $regles = [
            'statut' => 'sometimes|in:a_faire,en_cours,termine,bloque',
            'notes_client' => 'nullable|string|max:2000',
        ];

        // User avec permission d'edition des plans : peut aussi modifier titre, description,
        // priorite, echeance, notes consultant. Sinon (typiquement role client) : status/notes_client uniquement.
        if ($user->hasPermissionTo('update-plans-actions')) {
            $regles = array_merge($regles, [
                'titre' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'priorite' => 'sometimes|in:p1,p2,p3,p4',
                'echeance' => 'nullable|date',
                'notes_consultant' => 'nullable|string|max:2000',
                'responsable_id' => 'nullable|exists:users,id',
            ]);
        }

        $data = $request->validate($regles);

        if (isset($data['statut']) && $data['statut'] === 'termine' && $item->statut !== 'termine') {
            $data['termine_le'] = now();
        }
        if (isset($data['statut']) && $data['statut'] !== 'termine') {
            $data['termine_le'] = null;
        }

        $item->update($data);

        // Si toutes les actions sont terminees, passer le plan en "en_cours" si ce n'est pas deja fait
        if ($plan->statut === 'accepte_client' && $item->statut === 'termine') {
            $plan->update(['statut' => 'en_cours']);
        }

        return response()->json(['item' => $item->fresh()->load('responsable:id,nom,prenom')]);
    }

    public function supprimerItem(Request $request, PlanAction $plan, PlanActionItem $item): JsonResponse
    {
        $this->authorize('update', $plan);
        abort_unless($item->plan_action_id === $plan->id, 404);

        $item->delete();

        return response()->json(['message' => 'Action supprimee.']);
    }

    /**
     * Soumet le plan au consultant : declenche la verification LLM des preuves
     * uploadees par le client sur chaque item. NE re-detecte PAS de nouveaux
     * ecarts — c'est une evaluation ciblee preuve vs recommandation.
     *
     * Conditions :
     *  - User doit pouvoir mettre a jour les items (client_admin ou consultant)
     *  - Plan doit etre accepte (statut accepte_client ou en_cours)
     *  - Plan pas deja en cours de verification
     */
    public function soumettre(Request $request, PlanAction $plan): JsonResponse
    {
        $this->authorize('mettreAJourItem', $plan);

        if (! in_array($plan->statut, ['accepte_client', 'en_cours'], true)) {
            return response()->json([
                'message' => 'Le plan doit etre accepte avant d\'etre soumis pour validation.',
            ], 422);
        }

        if ($plan->verification_statut === 'en_cours') {
            return response()->json([
                'message' => 'Une verification est deja en cours sur ce plan.',
            ], 422);
        }

        // Au moins un item avec une preuve — sinon le job ne pourrait rien evaluer.
        $nbItemsAvecPreuves = $plan->items()->whereHas('preuves')->count();
        if ($nbItemsAvecPreuves === 0) {
            return response()->json([
                'message' => 'Aucune preuve uploadee. Ajoutez au moins une preuve sur un item avant de soumettre.',
            ], 422);
        }

        // Reset verdicts precedents (cas d'une re-soumission apres rouvrir).
        $plan->items()->update([
            'verdict_correction' => null,
            'justification_correction' => null,
            'verifie_le' => null,
        ]);

        $plan->update([
            'soumis_le' => now(),
            'soumis_par' => $request->user()->id,
            'verification_statut' => 'en_attente',
            'verification_progression_pct' => 0,
        ]);

        // dispatchAfterResponse permet de repondre 200 a l'utilisateur immediatement,
        // puis de tourner la verification en arriere-plan (sans worker requis).
        \App\Jobs\VerifierPreuvesPlanJob::dispatchAfterResponse($plan);

        $this->audit->log('plan_action.soumis', [
            'plan_id' => $plan->id,
            'nb_items_avec_preuves' => $nbItemsAvecPreuves,
        ]);

        return response()->json([
            'message' => 'Plan soumis. La verification des preuves est lancee en arriere-plan.',
            'plan' => $plan->fresh(),
        ]);
    }

    /**
     * Rouvre un plan deja soumis : annule la soumission et remet les preuves
     * en mode editable pour le client. Reservee aux consultants ASC.
     */
    public function rouvrir(Request $request, PlanAction $plan): JsonResponse
    {
        $this->authorize('update', $plan);

        if (! $plan->soumis_le) {
            return response()->json([
                'message' => 'Le plan n\'a pas encore ete soumis — rien a rouvrir.',
            ], 422);
        }

        if ($plan->verification_statut === 'en_cours') {
            return response()->json([
                'message' => 'Verification en cours, attendez la fin avant de rouvrir.',
            ], 422);
        }

        $plan->update([
            'soumis_le' => null,
            'soumis_par' => null,
            'verification_statut' => null,
            'verification_progression_pct' => null,
        ]);

        $this->audit->log('plan_action.rouvert', ['plan_id' => $plan->id]);

        return response()->json([
            'message' => 'Plan rouvert : le client peut modifier les preuves.',
            'plan' => $plan->fresh(),
        ]);
    }
}
