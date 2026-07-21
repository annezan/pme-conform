<?php

/**
 * Contrôleur SecteurActiviteController — Gestion des secteurs d'activité.
 *
 * API CRUD pour la gestion des secteurs d'activité normalisés.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SecteurActivite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\PathItem(
    path: "/api/secteurs-activite",
    get: new OA\Get(
        operationId: "secteurs-activite-index",
        summary: "Lister tous les secteurs d'activité",
        description: "Retourne la liste paginée des secteurs d'activité avec filtres optionnels",
        tags: ["Secteurs d'activité"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "search",
                in: "query",
                description: "Terme de recherche",
                required: false,
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "is_actif",
                in: "query",
                description: "Filtrer par statut actif/inactif",
                required: false,
                schema: new OA\Schema(type: "boolean")
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
                description: "Liste des secteurs d'activité",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/SecteurActivite")
                        ),
                        new OA\Property(
                            property: "meta",
                            type: "object",
                            properties: [
                                new OA\Property(property: "current_page", type: "integer"),
                                new OA\Property(property: "last_page", type: "integer"),
                                new OA\Property(property: "per_page", type: "integer"),
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
        operationId: "secteurs-activite-store",
        summary: "Créer un secteur d'activité",
        description: "Crée un nouveau secteur d'activité avec les permissions requises",
        tags: ["Secteurs d'activité"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["nom"],
                properties: [
                    new OA\Property(property: "nom", type: "string", maxLength: 255, example: "Technologie"),
                    new OA\Property(property: "description", type: "string", maxLength: 1000, example: "Entreprises technologiques et startups"),
                    new OA\Property(property: "code", type: "string", maxLength: 50, example: "TECH"),
                    new OA\Property(property: "is_actif", type: "boolean", example: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Secteur d'activité créé avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", ref: "#/components/schemas/SecteurActivite"),
                        new OA\Property(property: "message", type: "string", example: "Secteur d'activité créé avec succès")
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
    path: "/api/secteurs-activite/{id}",
    get: new OA\Get(
        operationId: "secteurs-activite-show",
        summary: "Afficher un secteur d'activité",
        description: "Retourne les détails complets d'un secteur d'activité avec ses relations",
        tags: ["Secteurs d'activité"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID du secteur d'activité",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Détails du secteur d'activité",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", ref: "#/components/schemas/SecteurActivite")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 404, description: "Secteur d'activité non trouvé")
        ]
    ),
    put: new OA\Put(
        operationId: "secteurs-activite-update",
        summary: "Mettre à jour un secteur d'activité",
        description: "Met à jour les informations d'un secteur d'activité existant",
        tags: ["Secteurs d'activité"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID du secteur d'activité",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "nom", type: "string", maxLength: 255, example: "Technologie"),
                    new OA\Property(property: "description", type: "string", maxLength: 1000, example: "Entreprises technologiques et startups"),
                    new OA\Property(property: "code", type: "string", maxLength: 50, example: "TECH"),
                    new OA\Property(property: "is_actif", type: "boolean", example: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Secteur d'activité mis à jour avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", ref: "#/components/schemas/SecteurActivite"),
                        new OA\Property(property: "message", type: "string", example: "Secteur d'activité mis à jour avec succès")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé"),
            new OA\Response(response: 404, description: "Secteur d'activité non trouvé"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    ),
    delete: new OA\Delete(
        operationId: "secteurs-activite-destroy",
        summary: "Supprimer un secteur d'activité",
        description: "Supprime (soft delete) un secteur d'activité avec vérifications de sécurité",
        tags: ["Secteurs d'activité"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID du secteur d'activité",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Secteur d'activité supprimé avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Secteur d'activité supprimé avec succès"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "integer"),
                                new OA\Property(property: "deleted_at", type: "string", format: "date-time")
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé"),
            new OA\Response(response: 404, description: "Secteur d'activité non trouvé"),
            new OA\Response(
                response: 422,
                description: "Impossible de supprimer (utilisé par des clients/référentiels)",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string"),
                        new OA\Property(
                            property: "details",
                            type: "object",
                            properties: [
                                new OA\Property(property: "clients_count", type: "integer"),
                                new OA\Property(property: "referentiels_count", type: "integer")
                            ]
                        )
                    ]
                )
            )
        ]
    )
)]

#[OA\PathItem(
    path: "/api/secteurs-activite/{id}/toggle-actif",
    patch: new OA\Patch(
        operationId: "secteurs-activite-toggle",
        summary: "Activer/Désactiver un secteur d'activité",
        description: "Inverse le statut actif/inactif d'un secteur d'activité",
        tags: ["Secteurs d'activité"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID du secteur d'activité",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Statut mis à jour avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", ref: "#/components/schemas/SecteurActivite"),
                        new OA\Property(property: "message", type: "string", example: "Secteur d'activité activé avec succès")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 404, description: "Secteur d'activité non trouvé")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/secteurs-activite/liste",
    get: new OA\Get(
        operationId: "secteurs-activite-liste",
        summary: "Liste des secteurs actifs",
        description: "Retourne la liste des secteurs d'activité actifs pour les formulaires",
        tags: ["Secteurs d'activité"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des secteurs actifs",
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
                                    new OA\Property(property: "code", type: "string")
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

class SecteurActiviteController extends Controller
{
    /**
     * Liste tous les secteurs d'activité.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SecteurActivite::query();

        // Filtres
        if ($request->filled('search')) {
            $query->recherche($request->input('search'));
        }

        if ($request->filled('is_actif')) {
            if ($request->boolean('is_actif')) {
                $query->actif();
            } else {
                $query->inactif();
            }
        }

        $secteurs = $query->orderBy('nom')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => $secteurs->items(),
            'meta' => [
                'current_page' => $secteurs->currentPage(),
                'last_page' => $secteurs->lastPage(),
                'per_page' => $secteurs->perPage(),
                'total' => $secteurs->total(),
            ],
        ]);
    }

    /**
     * Crée un nouveau secteur d'activité.
     * 
     * @OA\Post(
     *     path="/api/secteurs-activite",
     *     tags={"Secteurs d'activité"},
     *     summary="Créer un secteur d'activité",
     *     description="Crée un nouveau secteur d'activité avec les permissions requises",
     *     security={{"bearerAuth"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nom"},
     *             @OA\Property(property="nom", type="string", maxLength=255, example="Technologie"),
     *             @OA\Property(property="description", type="string", maxLength=1000, example="Entreprises technologiques et startups"),
     *             @OA\Property(property="code", type="string", maxLength=50, example="TECH"),
     *             @OA\Property(property="is_actif", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Secteur d'activité créé avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", ref="#/components/schemas/SecteurActivite"),
     *             @OA\Property(property="message", type="string", example="Secteur d'activité créé avec succès")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=403, description="Non autorisé"),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        // Vérifier les permissions
        if (!$request->user()->can('create-secteurs')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'nom' => 'required|string|max:255|unique:secteurs_activite,nom',
            'description' => 'required|string|min:10|max:5000',
            'code' => 'nullable|string|max:50|unique:secteurs_activite,code',
            'is_actif' => 'boolean',
        ]);

        $validated['created_by'] = $request->user()->id;
        $secteur = SecteurActivite::create($validated);

        return response()->json([
            'data' => $secteur->load(['createdBy:id,nom,prenom', 'updatedBy:id,nom,prenom']),
            'message' => 'Secteur d\'activité créé avec succès',
        ], 201);
    }

    /**
     * Affiche un secteur d'activité spécifique.
     * 
     * @OA\Get(
     *     path="/api/secteurs-activite/{id}",
     *     tags={"Secteurs d'activité"},
     *     summary="Afficher un secteur d'activité",
     *     description="Retourne les détails complets d'un secteur d'activité avec ses relations",
     *     security={{"bearerAuth"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du secteur d'activité",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails du secteur d'activité",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", ref="#/components/schemas/SecteurActivite")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=404, description="Secteur d'activité non trouvé")
     * )
     */
    public function show(SecteurActivite $secteurs_activite): JsonResponse
    {
        // Retourner les données du secteur sans relations pour éviter les erreurs
        return response()->json([
            'data' => [
                'id' => $secteurs_activite->id,
                'nom' => $secteurs_activite->nom,
                'description' => $secteurs_activite->description,
                'code' => $secteurs_activite->code,
                'is_actif' => $secteurs_activite->is_actif,
                'created_at' => $secteurs_activite->created_at,
                'updated_at' => $secteurs_activite->updated_at,
                'deleted_at' => $secteurs_activite->deleted_at,
                'created_by' => $secteurs_activite->created_by,
                'updated_by' => $secteurs_activite->updated_by,
                'deleted_by' => $secteurs_activite->deleted_by,
            ],
        ]);
    }

    /**
     * Met à jour un secteur d'activité.
     * 
     * @OA\Put(
     *     path="/api/secteurs-activite/{id}",
     *     tags={"Secteurs d'activité"},
     *     summary="Mettre à jour un secteur d'activité",
     *     description="Met à jour les informations d'un secteur d'activité existant",
     *     security={{"bearerAuth"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du secteur d'activité",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="nom", type="string", maxLength=255, example="Technologie"),
     *             @OA\Property(property="description", type="string", maxLength=1000, example="Entreprises technologiques et startups"),
     *             @OA\Property(property="code", type="string", maxLength=50, example="TECH"),
     *             @OA\Property(property="is_actif", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Secteur d'activité mis à jour avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", ref="#/components/schemas/SecteurActivite"),
     *             @OA\Property(property="message", type="string", example="Secteur d'activité mis à jour avec succès")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=403, description="Non autorisé"),
     *     @OA\Response(response=404, description="Secteur d'activité non trouvé"),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function update(Request $request, SecteurActivite $secteurs_activite): JsonResponse
    {
        // Vérifier les permissions
        $user = $request->user();
        if (!$user->can('update-secteurs')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'nom' => [
                'required',
                'string',
                'max:255',
                'unique:secteurs_activite,nom,' . $secteurs_activite->id,
            ],
            'description' => 'required|string|min:10|max:5000',
            'code' => [
                'nullable',
                'string',
                'max:50',
                'unique:secteurs_activite,code,' . $secteurs_activite->id,
            ],
            'is_actif' => 'boolean',
        ]);

        $validated['updated_by'] = $request->user()->id;
        $secteurs_activite->update($validated);

        return response()->json([
            'data' => $secteurs_activite->load(['createdBy:id,nom,prenom', 'updatedBy:id,nom,prenom']),
            'message' => 'Secteur d\'activité mis à jour avec succès',
        ]);
    }

    /**
     * Supprime (soft delete) un secteur d'activité.
     * 
     * Cette fonction effectue un soft delete pour :
     * - Préserver l'intégrité des données historiques
     * - Maintenir la traçabilité (audit)
     * - Permettre la restauration en cas d'erreur
     * - Conformer aux exigences RGPD
     * 
     * @OA\Delete(
     *     path="/api/secteurs-activite/{id}",
     *     tags={"Secteurs d'activité"},
     *     summary="Supprimer un secteur d'activité",
     *     description="Supprime (soft delete) un secteur d'activité avec vérifications de sécurité",
     *     security={{"bearerAuth"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du secteur d'activité",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Secteur d'activité supprimé avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Secteur d'activité supprimé avec succès"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="deleted_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=403, description="Non autorisé"),
     *     @OA\Response(response=404, description="Secteur d'activité non trouvé"),
     *     @OA\Response(
     *         response=422,
     *         description="Impossible de supprimer (utilisé par des clients/référentiels)",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="details", type="object",
     *                 @OA\Property(property="clients_count", type="integer"),
     *                 @OA\Property(property="referentiels_count", type="integer")
     *             )
     *         )
     *     )
     * )
     * 
     * @param Request $request
     * @param SecteurActivite $secteurActivite
     * @return JsonResponse
     */
    public function destroy(Request $request, SecteurActivite $secteurs_activite): JsonResponse
    {
        // 1. Vérifier les permissions
        $user = $request->user();
        if (!$user->can('delete-secteurs')) {
            return response()->json([
                'message' => 'Non autorisé',
                'debug' => [
                    'user_id' => $user->id,
                    'permissions' => $user->role
                        ? $user->role->permissions()->pluck('name')
                        : collect(),
                    'can_delete' => $user->can('delete-secteurs')
                ]
            ], 403);
        }

        // 2. Vérifier si le secteur est utilisé (empêcher la suppression si actif)
        if ($secteurs_activite->clients()->exists() || $secteurs_activite->referentiels()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer ce secteur d\'activité car il est utilisé par des clients ou des référentiels',
                'details' => [
                    'clients_count' => $secteurs_activite->clients()->count(),
                    'referentiels_count' => $secteurs_activite->referentiels()->count()
                ]
            ], 422);
        }

        // 3. Soft delete avec audit
        $secteurs_activite->update([
            'deleted_by' => $request->user()->id,
            'updated_by' => $request->user()->id
        ]);
        $secteurs_activite->delete();

        return response()->json([
            'message' => 'Secteur d\'activité supprimé avec succès',
            'data' => [
                'id' => $secteurs_activite->id,
                'deleted_at' => $secteurs_activite->deleted_at,
                // 'note' => 'Soft delete - les données sont conservées pour audit'
            ]
        ]);
    }

    /**
     * Active ou désactive un secteur d'activité.
     * 
     * @OA\Patch(
     *     path="/api/secteurs-activite/{id}/toggle-actif",
     *     tags={"Secteurs d'activité"},
     *     summary="Activer/Désactiver un secteur d'activité",
     *     description="Inverse le statut actif/inactif d'un secteur d'activité",
     *     security={{"bearerAuth"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID du secteur d'activité",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statut mis à jour avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", ref="#/components/schemas/SecteurActivite"),
     *             @OA\Property(property="message", type="string", example="Secteur d'activité activé avec succès")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=404, description="Secteur d'activité non trouvé")
     * )
     */
    public function toggleActif(Request $request, SecteurActivite $secteurActivite): JsonResponse
    {
        $secteurActivite->update([
            'is_actif' => !$secteurActivite->is_actif,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => $secteurActivite->load(['createdBy:id,nom,prenom', 'updatedBy:id,nom,prenom']),
            'message' => $secteurActivite->is_actif 
                ? 'Secteur d\'activité activé avec succès'
                : 'Secteur d\'activité désactivé avec succès',
        ]);
    }

    /**
     * Retourne la liste des secteurs d'activité actifs (pour les formulaires).
     * 
     * @OA\Get(
     *     path="/api/secteurs-activite/liste",
     *     tags={"Secteurs d'activité"},
     *     summary="Liste des secteurs actifs",
     *     description="Retourne la liste des secteurs d'activité actifs pour les formulaires",
     *     security={{"bearerAuth"={}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des secteurs actifs",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="nom", type="string"),
     *                 @OA\Property(property="code", type="string")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function liste(): JsonResponse
    {
        $secteurs = SecteurActivite::actif()
            ->orderBy('nom')
            ->get(['id', 'nom', 'code']);

        return response()->json([
            'data' => $secteurs,
        ]);
    }
}
