<?php

/**
 * Controleur MissionController — CRUD des missions.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MatriceCollecte;
use App\Models\Mission;
use App\Models\QuestionnaireGenere;
use App\Services\Methode2\MatriceTemplate;
use App\Services\Methode3\AuditFlashTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(
    path: "/api/missions",
    get: new OA\Get(
        operationId: "missions-index",
        summary: "Lister toutes les missions",
        description: "Retourne la liste paginée des missions avec filtres optionnels",
        tags: ["Missions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "statut",
                in: "query",
                description: "Filtrer par statut (brouillon, en_cours, en_revue, termine, archive)",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["brouillon", "en_cours", "en_revue", "termine", "archive"])
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
                description: "Liste des missions",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/Mission")
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
        operationId: "missions-store",
        summary: "Créer une mission",
        description: "Crée une nouvelle mission avec initialisation automatique selon la méthode choisie",
        tags: ["Missions"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["client_id", "titre"],
                properties: [
                    new OA\Property(property: "client_id", type: "integer", example: 1),
                    new OA\Property(property: "titre", type: "string", maxLength: 255, example: "Audit de conformité RGPD"),
                    new OA\Property(property: "description", type: "string", nullable: true, example: "Audit complet de conformité RGPD pour l'entreprise"),
                    new OA\Property(property: "type", type: "string", enum: ["audit_conformite", "accompagnement", "formation", "aipd", "declaration_artci", "autre"], nullable: true, example: "audit_conformite"),
                    new OA\Property(property: "methode", type: "string", enum: ["methode_1", "methode_2"], nullable: true, example: "methode_2"),
                    new OA\Property(property: "priorite", type: "string", enum: ["basse", "normale", "haute", "urgente"], nullable: true, example: "normale"),
                    new OA\Property(property: "date_debut", type: "string", format: "date", nullable: true, example: "2024-01-15"),
                    new OA\Property(property: "date_echeance", type: "string", format: "date", nullable: true, example: "2024-03-15"),
                    new OA\Property(property: "notes_internes", type: "string", nullable: true, example: "Attention aux délais serrés")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Mission créée avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "mission", ref: "#/components/schemas/Mission")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/missions/{id}",
    get: new OA\Get(
        operationId: "missions-show",
        summary: "Afficher une mission",
        description: "Retourne les détails complets d'une mission avec ses documents et conversations",
        tags: ["Missions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID de la mission",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Détails de la mission",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "mission", ref: "#/components/schemas/Mission")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 404, description: "Mission non trouvée")
        ]
    ),
    put: new OA\Put(
        operationId: "missions-update",
        summary: "Mettre à jour une mission",
        description: "Met à jour les informations d'une mission existante",
        tags: ["Missions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID de la mission",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "titre", type: "string", maxLength: 255, nullable: true, example: "Audit RGPD mis à jour"),
                    new OA\Property(property: "description", type: "string", nullable: true, example: "Description mise à jour"),
                    new OA\Property(property: "type", type: "string", enum: ["audit_conformite", "accompagnement", "formation", "aipd", "declaration_artci", "autre"], nullable: true),
                    new OA\Property(property: "statut", type: "string", enum: ["brouillon", "en_cours", "en_revue", "termine", "archive"], nullable: true, example: "en_revue"),
                    new OA\Property(property: "priorite", type: "string", enum: ["basse", "normale", "haute", "urgente"], nullable: true),
                    new OA\Property(property: "progression", type: "integer", nullable: true, minimum: 0, maximum: 100, example: 75),
                    new OA\Property(property: "date_debut", type: "string", format: "date", nullable: true),
                    new OA\Property(property: "date_echeance", type: "string", format: "date", nullable: true),
                    new OA\Property(property: "date_cloture", type: "string", format: "date", nullable: true),
                    new OA\Property(property: "notes_internes", type: "string", nullable: true, example: "Notes mises à jour")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Mission mise à jour avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "mission", ref: "#/components/schemas/Mission")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 404, description: "Mission non trouvée"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    ),
    delete: new OA\Delete(
        operationId: "missions-destroy",
        summary: "Supprimer une mission",
        description: "Supprime une mission de manière irréversible",
        tags: ["Missions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID de la mission",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Mission supprimée avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Mission supprimée.")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 404, description: "Mission non trouvée")
        ]
    )
)]

class MissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // On charge aussi les secteurs du client : l'etape « Referentiels » de la
        // page Nouvelle Analyse en a besoin pour filtrer les referentiels affiches.
        $query = Mission::with([
            'client:id,raison_sociale',
            'client.secteursActivite:id,nom',
            'responsable:id,nom,prenom',
        ]);

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        // User sans permission de gestion globale : voit les missions dont il est
        // le responsable historique OU le createur OU dont il est affecte via
        // la pivot mission_user. Manager/admin voient tout via view-all-missions.
        $user = $request->user();
        if (! $user->hasPermissionTo('view-all-missions')) {
            $query->where(function ($q) use ($user) {
                $q->where('responsable_id', $user->id)
                    ->orWhere('created_by', $user->id)
                    ->orWhereHas('consultants', fn ($qc) => $qc->where('users.id', $user->id));
            });
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'titre' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|in:audit_conformite,accompagnement,formation,aipd,declaration_artci,autre',
            'methode' => 'nullable|in:methode_1,methode_2,methode_3',
            'priorite' => 'nullable|in:basse,normale,haute,urgente',
            'date_debut' => 'nullable|date',
            'date_echeance' => 'nullable|date|after_or_equal:date_debut',
            'notes_internes' => 'nullable|string',
        ]);

        $data['type'] = $data['type'] ?? 'audit_conformite';
        $data['methode'] = $data['methode'] ?? 'methode_1';

        $data['responsable_id'] = $request->user()->id;
        $data['created_by'] = $request->user()->id;
        $data['reference'] = Mission::genererReference();
        $data['statut'] = 'brouillon';

        $mission = Mission::create($data);

        // Auto-affectation du createur via la pivot mission_user : il devient
        // le premier membre de la mission, avec le role 'createur'. Cela
        // garantit que le scope "voir mes missions" (pivot) fonctionne
        // immediatement, sans dependre de l'ancienne colonne responsable_id.
        $mission->consultants()->syncWithoutDetaching([
            $request->user()->id => [
                'role_dans_mission' => 'createur',
                'affecte_le' => now(),
                'affecte_par' => $request->user()->id,
            ],
        ]);

        // Methode 1 : on cree un formulaire vierge attache a la mission, que
        // le client (ou l'agent AS Consulting au cours d'un interview) pourra
        // editer puis remplir. Methode 2 cree ses formulaires apres l'organigramme.
        if ($mission->methode === 'methode_1') {
            QuestionnaireGenere::create([
                'mission_id' => $mission->id,
                'pole' => 'Formulaire mission',
                'titre' => 'Formulaire de collecte — ' . $mission->titre,
                'description' => 'Formulaire concu par AS Consulting. A renseigner par le client (ou par l\'agent au cours d\'un interview).',
                'questions' => [],
                'source' => 'manuel',
                'themes' => [],
                'statut' => 'brouillon',
                'genere_par' => $request->user()->id,
            ]);
        }

        // Methode 2 : on initialise la matrice de collecte avec le template
        // structure (5 poles) afin que le client puisse renseigner directement
        // depuis son espace, sans attendre l'envoi par AS Consulting.
        if ($mission->methode === 'methode_2') {
            MatriceCollecte::firstOrCreate(
                ['mission_id' => $mission->id],
                ['statut' => 'a_remplir', 'reponses' => MatriceTemplate::defaut()]
            );
        }

        // Methode 3 : Audit Flash. Pas de matrice ni d'organigramme : on
        // injecte directement le questionnaire fige (10 questions Oui/Non/NSP)
        // dans l'espace du client.
        if ($mission->methode === 'methode_3') {
            QuestionnaireGenere::create([
                'mission_id' => $mission->id,
                'pole' => 'Audit Flash',
                'titre' => 'Audit Flash — Scan Pénal du Dirigeant',
                'description' => AuditFlashTemplate::description(),
                'questions' => AuditFlashTemplate::questions(),
                'source' => 'manuel',
                'themes' => AuditFlashTemplate::themes(),
                'statut' => 'envoye',
                'genere_par' => $request->user()->id,
                'envoye_a' => now(),
            ]);
        }

        return response()->json(['mission' => $mission->load('client:id,raison_sociale')], 201);
    }

    public function show(Request $request, Mission $mission): JsonResponse
    {
        $this->authorize('view', $mission);

        $mission->load([
            'client:id,raison_sociale,sigle',
            'responsable:id,nom,prenom',
            'createur:id,nom,prenom',
            'consultants:id,nom,prenom,email',
            'documents' => fn ($q) => $q->latest()->take(20),
            'conversations' => fn ($q) => $q->with('agent:id,nom,slug')->latest()->take(10),
        ]);

        $user = $request->user();

        return response()->json([
            'mission' => $mission,
            'peut_attacher_consultants' => $user->can('attacherConsultants', $mission),
            'peut_supprimer' => $user->can('delete', $mission),
        ]);
    }

    public function update(Request $request, Mission $mission): JsonResponse
    {
        $data = $request->validate([
            'titre' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|in:audit_conformite,accompagnement,formation,aipd,declaration_artci,autre',
            'statut' => 'sometimes|in:brouillon,en_cours,en_revue,termine,archive',
            'priorite' => 'nullable|in:basse,normale,haute,urgente',
            'progression' => 'nullable|numeric|min:0|max:100',
            'date_debut' => 'nullable|date',
            'date_echeance' => 'nullable|date',
            'date_cloture' => 'nullable|date',
            'notes_internes' => 'nullable|string',
        ]);

        $data['updated_by'] = $request->user()->id;
        $mission->update($data);

        return response()->json(['mission' => $mission->fresh()->load('client:id,raison_sociale')]);
    }

    public function destroy(Request $request, Mission $mission): JsonResponse
    {
        $mission->update(['deleted_by' => $request->user()->id]);
        $mission->delete();

        return response()->json(['message' => 'Mission supprimee.']);
    }

    /**
     * Liste les consultants affectes a une mission (pivot mission_user).
     * Utilise par la fiche mission pour afficher la section "Consultants".
     */
    public function listerConsultants(Request $request, Mission $mission): JsonResponse
    {
        $this->authorize('view', $mission);

        $consultants = $mission->consultants()
            ->select('users.id', 'users.nom', 'users.prenom', 'users.email')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'nom' => $u->nom,
                'prenom' => $u->prenom,
                'email' => $u->email,
                'role_dans_mission' => $u->pivot->role_dans_mission,
                'affecte_le' => $u->pivot->affecte_le,
                'est_createur' => $u->id === $mission->created_by,
            ]);

        return response()->json(['consultants' => $consultants]);
    }

    /**
     * Attache un ou plusieurs consultants a la mission. Le user courant doit
     * etre le createur, ou avoir view-all-missions.
     */
    public function attacherConsultants(Request $request, Mission $mission): JsonResponse
    {
        $this->authorize('attacherConsultants', $mission);

        $data = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        // Filtre par PERMISSION (pas par nom de role) : seuls les users dont
        // le role detient au moins view-missions peuvent etre affectes. Cette
        // approche est extensible : un nouveau role custom cree via l'UI admin
        // et qui recoit view-missions devient automatiquement candidat, sans
        // modification de code. Empeche d'affecter un client_admin/client par
        // erreur puisqu'ils n'ont pas view-missions par defaut.
        $userIdsAutorises = \App\Models\User::whereIn('id', $data['user_ids'])
            ->whereHas('role.permissions', fn ($q) => $q->where('name', 'view-missions'))
            ->pluck('id')
            ->all();

        $ignoreCount = count($data['user_ids']) - count($userIdsAutorises);

        $affectations = [];
        foreach ($userIdsAutorises as $uid) {
            $affectations[$uid] = [
                'role_dans_mission' => 'consultant',
                'affecte_le' => now(),
                'affecte_par' => $request->user()->id,
            ];
        }
        $mission->consultants()->syncWithoutDetaching($affectations);

        $message = count($userIdsAutorises) . ' consultant(s) affecte(s).';
        if ($ignoreCount > 0) {
            $message .= " {$ignoreCount} ignore(s) : role incompatible.";
        }

        return response()->json([
            'message' => $message,
            'consultants_affectes' => $userIdsAutorises,
        ]);
    }

    /**
     * Retire un consultant de la mission. Impossible de retirer le createur.
     */
    public function detacherConsultant(Request $request, Mission $mission, \App\Models\User $user): JsonResponse
    {
        $this->authorize('detacherConsultant', [$mission, $user]);

        if ($user->id === $mission->created_by) {
            return response()->json([
                'message' => 'Impossible de retirer le createur de la mission.',
            ], 422);
        }

        $mission->consultants()->detach($user->id);

        return response()->json(['message' => 'Consultant retire de la mission.']);
    }

    /**
     * Liste les users candidats a une affectation sur la mission :
     * roles ASC (admin/manager/consultant) qui ne sont PAS deja affectes.
     * Utilise par la modale "Ajouter un consultant" cote UI.
     */
    public function candidatsConsultants(Request $request, Mission $mission): JsonResponse
    {
        $this->authorize('view', $mission);

        $dejaAffectes = $mission->consultants()->pluck('users.id')->all();

        // Candidats = tous les users actifs dont le role detient la permission
        // view-missions. Approche 100% dynamique : n'importe quel nouveau role
        // custom cree via l'UI admin apparait automatiquement des qu'il a la
        // permission requise.
        $candidats = \App\Models\User::whereHas('role.permissions', fn ($q) =>
                $q->where('name', 'view-missions'))
            ->whereNotIn('id', $dejaAffectes)
            ->where('is_active', true)
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get(['id', 'nom', 'prenom', 'email'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'nom' => $u->nom,
                'prenom' => $u->prenom,
                'email' => $u->email,
                'role' => $u->role?->name,
            ]);

        return response()->json(['candidats' => $candidats]);
    }
}
