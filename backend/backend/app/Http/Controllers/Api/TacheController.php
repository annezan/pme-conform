<?php

/**
 * TacheController — CRUD des taches assignees aux agents AS Consulting.
 *
 * Filtrage automatique par scope :
 *   - 'taches.view_all' : voit toutes les taches
 *   - 'taches.view_mine' : voit uniquement les taches qui lui sont assignees
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(
    path: "/api/taches",
    get: new OA\Get(
        operationId: "taches-index",
        summary: "Lister les tâches",
        description: "Retourne la liste des tâches avec filtres selon les permissions de l'utilisateur",
        tags: ["Tâches"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "statut",
                in: "query",
                description: "Filtrer par statut",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["a_faire", "en_cours", "terminee", "annulee"])
            ),
            new OA\Parameter(
                name: "client_id",
                in: "query",
                description: "Filtrer par client",
                required: false,
                schema: new OA\Schema(type: "integer")
            ),
            new OA\Parameter(
                name: "assignee_id",
                in: "query",
                description: "Filtrer par assigné",
                required: false,
                schema: new OA\Schema(type: "integer")
            ),
            new OA\Parameter(
                name: "mines",
                in: "query",
                description: "Afficher uniquement mes tâches",
                required: false,
                schema: new OA\Schema(type: "boolean")
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
                description: "Liste des tâches",
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
                                    new OA\Property(property: "description", type: "string", nullable: true),
                                    new OA\Property(property: "statut", type: "string", enum: ["a_faire", "en_cours", "terminee", "annulee"]),
                                    new OA\Property(property: "priorite", type: "string", enum: ["basse", "normale", "haute", "urgente"]),
                                    new OA\Property(property: "echeance", type: "string", format: "date", nullable: true),
                                    new OA\Property(property: "client", type: "object", properties: [
                                        new OA\Property(property: "id", type: "integer"),
                                        new OA\Property(property: "raison_sociale", type: "string")
                                    ]),
                                    new OA\Property(property: "mission", type: "object", properties: [
                                        new OA\Property(property: "id", type: "integer"),
                                        new OA\Property(property: "reference", type: "string"),
                                        new OA\Property(property: "titre", type: "string")
                                    ]),
                                    new OA\Property(property: "assignee", type: "object", nullable: true, properties: [
                                        new OA\Property(property: "id", type: "integer"),
                                        new OA\Property(property: "nom", type: "string"),
                                        new OA\Property(property: "prenom", type: "string")
                                    ]),
                                    new OA\Property(property: "createur", type: "object", properties: [
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

class TacheController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Tache::query()
            ->with(['client:id,raison_sociale', 'mission:id,reference,titre', 'assignee:id,nom,prenom', 'createur:id,nom,prenom']);

        if (! $user->can('taches.view_all')) {
            $query->where('assignee_id', $user->id);
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->filled('assignee_id')) {
            $query->where('assignee_id', $request->assignee_id);
        }
        if ($request->boolean('mines')) {
            $query->where('assignee_id', $user->id);
        }
        if ($request->boolean('ouvertes')) {
            $query->ouvertes();
        }

        return response()->json($query->orderByRaw("CASE statut WHEN 'en_cours' THEN 1 WHEN 'a_faire' THEN 2 WHEN 'bloquee' THEN 3 ELSE 4 END")->latest()->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('taches.assigner', Tache::class);

        $data = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'mission_id' => 'nullable|exists:missions,id',
            'assignee_id' => 'required|exists:users,id',
            'titre' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|in:recherche_ia,preparation_questionnaire,envoi_matrice,execution_analyse,redaction_rapport,revision_traitement,autre',
            'priorite' => 'nullable|in:basse,normale,haute,urgente',
            'echeance' => 'nullable|date',
        ]);
        $data['assignee_par'] = $request->user()->id;

        $tache = Tache::create($data);

        return response()->json(['tache' => $tache->load(['assignee:id,nom,prenom', 'client:id,raison_sociale'])], 201);
    }

    public function show(Tache $tache, Request $request): JsonResponse
    {
        $this->verifierAcces($request->user(), $tache);
        $tache->load(['client', 'mission', 'assignee:id,nom,prenom,email', 'createur:id,nom,prenom']);

        return response()->json(['tache' => $tache]);
    }

    public function update(Request $request, Tache $tache): JsonResponse
    {
        $this->verifierAcces($request->user(), $tache);

        $data = $request->validate([
            'titre' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'priorite' => 'nullable|in:basse,normale,haute,urgente',
            'statut' => 'nullable|in:a_faire,en_cours,bloquee,terminee,annulee',
            'echeance' => 'nullable|date',
            'commentaire_cloture' => 'nullable|string',
        ]);

        if (($data['statut'] ?? null) === 'en_cours' && ! $tache->demarree_a) {
            $data['demarree_a'] = now();
        }
        if (in_array($data['statut'] ?? null, ['terminee', 'annulee'], true) && ! $tache->terminee_a) {
            $data['terminee_a'] = now();
        }

        $tache->update($data);

        return response()->json(['tache' => $tache->fresh(['assignee:id,nom,prenom', 'client:id,raison_sociale'])]);
    }

    public function destroy(Request $request, Tache $tache): JsonResponse
    {
        if (! $request->user()->can('taches.assigner')) {
            abort(403, 'Seul un assignateur peut supprimer une tache.');
        }
        $tache->delete();

        return response()->json(['message' => 'Tache supprimee.']);
    }

    private function verifierAcces($user, Tache $tache): void
    {
        if ($user->can('taches.view_all')) {
            return;
        }
        if ($tache->assignee_id !== $user->id) {
            abort(403, 'Cette tache ne vous est pas assignee.');
        }
    }
}
