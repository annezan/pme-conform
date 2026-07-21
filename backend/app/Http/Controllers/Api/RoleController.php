<?php

/**
 * Contrôleur RoleController — Gestion des rôles et permissions.
 *
 * API CRUD pour la gestion des rôles avec assignation de permissions.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\PathItem(
    path: "/api/admin/roles",
    get: new OA\Get(
        operationId: "roles-index",
        summary: "Lister les rôles",
        description: "Retourne la liste paginée des rôles avec leurs permissions",
        tags: ["Rôles et Permissions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "active_only",
                in: "query",
                description: "Filtrer uniquement les rôles actifs",
                required: false,
                schema: new OA\Schema(type: "boolean", default: true)
            ),
            new OA\Parameter(
                name: "search",
                in: "query",
                description: "Recherche dans le nom du rôle",
                required: false,
                schema: new OA\Schema(type: "string")
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
                description: "Liste des rôles avec permissions",
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
                                    new OA\Property(property: "name", type: "string"),
                                    new OA\Property(property: "guard_name", type: "string"),
                                    new OA\Property(property: "is_active", type: "boolean"),
                                    new OA\Property(property: "created_at", type: "string", format: "date-time"),
                                    new OA\Property(property: "updated_at", type: "string", format: "date-time"),
                                    new OA\Property(property: "permissions", type: "array", items: new OA\Items(
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "id", type: "integer"),
                                            new OA\Property(property: "name", type: "string"),
                                            new OA\Property(property: "guard_name", type: "string")
                                        ]
                                    ))
                                ]
                            )
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
            new OA\Response(response: 401, description: "Non authentifié")
        ]
    ),
    post: new OA\Post(
        operationId: "roles-store",
        summary: "Créer un rôle",
        description: "Crée un nouveau rôle avec les permissions spécifiées",
        tags: ["Rôles et Permissions"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", type: "string", maxLength: 255, example: "Consultant"),
                    new OA\Property(property: "description", type: "string", maxLength: 1000, example: "Rôle pour les consultants"),
                    new OA\Property(property: "is_active", type: "boolean", example: true),
                    new OA\Property(property: "permissions", type: "array", items: new OA\Items(type: "integer"))
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Rôle créé avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object", properties: [
    new OA\Property(property: "id", type: "integer"),
    new OA\Property(property: "name", type: "string"),
    new OA\Property(property: "guard_name", type: "string"),
    new OA\Property(property: "is_active", type: "boolean"),
    new OA\Property(property: "created_at", type: "string", format: "date-time"),
    new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    new OA\Property(property: "permissions", type: "array", items: new OA\Items(
        type: "object",
        properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "name", type: "string"),
            new OA\Property(property: "guard_name", type: "string")
        ]
    ))
]),
                        new OA\Property(property: "message", type: "string", example: "Rôle créé avec succès")
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
    path: "/api/admin/roles/{role}",
    get: new OA\Get(
        operationId: "roles-show",
        summary: "Afficher un rôle",
        description: "Retourne les détails d'un rôle spécifique avec ses permissions",
        tags: ["Rôles et Permissions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "role",
                in: "path",
                description: "ID du rôle",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Détails du rôle avec permissions",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object", properties: [
    new OA\Property(property: "id", type: "integer"),
    new OA\Property(property: "name", type: "string"),
    new OA\Property(property: "guard_name", type: "string"),
    new OA\Property(property: "is_active", type: "boolean"),
    new OA\Property(property: "created_at", type: "string", format: "date-time"),
    new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    new OA\Property(property: "permissions", type: "array", items: new OA\Items(
        type: "object",
        properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "name", type: "string"),
            new OA\Property(property: "guard_name", type: "string")
        ]
    ))
])
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 404, description: "Rôle non trouvé")
        ]
    ),
    put: new OA\Put(
        operationId: "roles-update",
        summary: "Mettre à jour un rôle",
        description: "Met à jour les informations d'un rôle existant",
        tags: ["Rôles et Permissions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "role",
                in: "path",
                description: "ID du rôle",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", maxLength: 255, example: "Consultant Senior"),
                    new OA\Property(property: "description", type: "string", maxLength: 1000, example: "Rôle pour les consultants expérimentés"),
                    new OA\Property(property: "is_active", type: "boolean", example: true),
                    new OA\Property(property: "permissions", type: "array", items: new OA\Items(type: "integer"))
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Rôle mis à jour avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object", properties: [
    new OA\Property(property: "id", type: "integer"),
    new OA\Property(property: "name", type: "string"),
    new OA\Property(property: "guard_name", type: "string"),
    new OA\Property(property: "is_active", type: "boolean"),
    new OA\Property(property: "created_at", type: "string", format: "date-time"),
    new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    new OA\Property(property: "permissions", type: "array", items: new OA\Items(
        type: "object",
        properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "name", type: "string"),
            new OA\Property(property: "guard_name", type: "string")
        ]
    ))
]),
                        new OA\Property(property: "message", type: "string", example: "Rôle mis à jour avec succès")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé"),
            new OA\Response(response: 404, description: "Rôle non trouvé"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    ),
    delete: new OA\Delete(
        operationId: "roles-destroy",
        summary: "Supprimer un rôle",
        description: "Supprime un rôle avec vérifications de sécurité",
        tags: ["Rôles et Permissions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "role",
                in: "path",
                description: "ID du rôle",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Rôle supprimé avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Rôle supprimé avec succès")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé"),
            new OA\Response(response: 404, description: "Rôle non trouvé"),
            new OA\Response(
                response: 422,
                description: "Impossible de supprimer (utilisé par des utilisateurs)",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Impossible de supprimer ce rôle car il est assigné à des utilisateurs")
                    ]
                )
            )
        ]
    )
)]

#[OA\PathItem(
    path: "/api/admin/roles/{role}/toggle-active",
    patch: new OA\Patch(
        operationId: "roles-toggle-active",
        summary: "Activer/Désactiver un rôle",
        description: "Inverse le statut actif/inactif d'un rôle",
        tags: ["Rôles et Permissions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "role",
                in: "path",
                description: "ID du rôle",
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
                        new OA\Property(property: "data", type: "object", properties: [
    new OA\Property(property: "id", type: "integer"),
    new OA\Property(property: "name", type: "string"),
    new OA\Property(property: "guard_name", type: "string"),
    new OA\Property(property: "is_active", type: "boolean"),
    new OA\Property(property: "created_at", type: "string", format: "date-time"),
    new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    new OA\Property(property: "permissions", type: "array", items: new OA\Items(
        type: "object",
        properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "name", type: "string"),
            new OA\Property(property: "guard_name", type: "string")
        ]
    ))
]),
                        new OA\Property(property: "message", type: "string", example: "Rôle activé avec succès")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé"),
            new OA\Response(response: 404, description: "Rôle non trouvé")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/admin/roles/{role}/attach-permissions",
    post: new OA\Post(
        operationId: "roles-attach-permissions",
        summary: "Ajouter des permissions à un rôle",
        description: "Ajoute les permissions spécifiées à un rôle existant",
        tags: ["Rôles et Permissions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "role",
                in: "path",
                description: "ID du rôle",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["permissions"],
                properties: [
                    new OA\Property(property: "permissions", type: "array", items: new OA\Items(type: "integer"))
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Permissions ajoutées avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object", properties: [
    new OA\Property(property: "id", type: "integer"),
    new OA\Property(property: "name", type: "string"),
    new OA\Property(property: "guard_name", type: "string"),
    new OA\Property(property: "is_active", type: "boolean"),
    new OA\Property(property: "created_at", type: "string", format: "date-time"),
    new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    new OA\Property(property: "permissions", type: "array", items: new OA\Items(
        type: "object",
        properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "name", type: "string"),
            new OA\Property(property: "guard_name", type: "string")
        ]
    ))
]),
                        new OA\Property(property: "message", type: "string", example: "Permissions ajoutées avec succès")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé"),
            new OA\Response(response: 404, description: "Rôle non trouvé"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/admin/roles/{role}/detach-permissions",
    post: new OA\Post(
        operationId: "roles-detach-permissions",
        summary: "Retirer des permissions d'un rôle",
        description: "Retire les permissions spécifiées d'un rôle existant",
        tags: ["Rôles et Permissions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "role",
                in: "path",
                description: "ID du rôle",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["permissions"],
                properties: [
                    new OA\Property(property: "permissions", type: "array", items: new OA\Items(type: "integer"))
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Permissions retirées avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "object", properties: [
    new OA\Property(property: "id", type: "integer"),
    new OA\Property(property: "name", type: "string"),
    new OA\Property(property: "guard_name", type: "string"),
    new OA\Property(property: "is_active", type: "boolean"),
    new OA\Property(property: "created_at", type: "string", format: "date-time"),
    new OA\Property(property: "updated_at", type: "string", format: "date-time"),
    new OA\Property(property: "permissions", type: "array", items: new OA\Items(
        type: "object",
        properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "name", type: "string"),
            new OA\Property(property: "guard_name", type: "string")
        ]
    ))
]),
                        new OA\Property(property: "message", type: "string", example: "Permissions retirées avec succès")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé"),
            new OA\Response(response: 404, description: "Rôle non trouvé"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/admin/permissions",
    get: new OA\Get(
        operationId: "permissions-index",
        summary: "Lister les permissions",
        description: "Retourne la liste paginée de toutes les permissions disponibles",
        tags: ["Rôles et Permissions"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "active_only",
                in: "query",
                description: "Filtrer uniquement les permissions actives",
                required: false,
                schema: new OA\Schema(type: "boolean", default: true)
            ),
            new OA\Parameter(
                name: "group",
                in: "query",
                description: "Filtrer par groupe de permissions",
                required: false,
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "search",
                in: "query",
                description: "Rechercher dans le nom des permissions",
                required: false,
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "per_page",
                in: "query",
                description: "Nombre d'éléments par page",
                required: false,
                schema: new OA\Schema(type: "integer", default: 50)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des permissions",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "integer"),
                                new OA\Property(property: "name", type: "string"),
                                new OA\Property(property: "guard_name", type: "string"),
                                new OA\Property(property: "group", type: "string"),
                                new OA\Property(property: "is_active", type: "boolean")
                            ]
                        ))
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/admin/roles-liste",
    get: new OA\Get(
        operationId: "roles-liste",
        summary: "Lister les rôles (formulaires)",
        description: "Retourne la liste des rôles actifs pour les formulaires",
        tags: ["Rôles et Permissions"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des rôles actifs",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(
                            type: "object",
                            properties: [
                                new OA\Property(property: "id", type: "integer"),
                                new OA\Property(property: "name", type: "string"),
                                new OA\Property(property: "description", type: "string")
                            ]
                        ))
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié")
        ]
    )
)]

class RoleController extends Controller
{
    /**
     * Liste tous les rôles avec leurs permissions.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Role::with('permissions');

        // Filtres
        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        $roles = $query->orderBy('name')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => $roles->items(),
            'meta' => [
                'current_page' => $roles->currentPage(),
                'last_page' => $roles->lastPage(),
                'per_page' => $roles->perPage(),
                'total' => $roles->total(),
            ],
        ]);
    }

    /**
     * Crée un rôle.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $validated['created_by'] = $request->user()->id;
        $role = Role::create($validated);

        // Synchroniser les permissions si fournies
        if (! empty($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        return response()->json([
            'data' => $role->load('permissions'),
            'message' => 'Rôle créé avec succès',
        ], 201);
    }

    /**
     * Affiche un rôle spécifique avec ses permissions.
     */
    public function show(Role $role): JsonResponse
    {
        $role->load('permissions');

        return response()->json([
            'data' => $role,
        ]);
    }

    /**
     * Met à jour un rôle.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($role->id),
            ],
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $validated['updated_by'] = $request->user()->id;
        $role->update($validated);

        // Synchroniser les permissions si fournies
        if (isset($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        return response()->json([
            'data' => $role->load('permissions'),
            'message' => 'Rôle mis à jour avec succès',
        ]);
    }

    /**
     * Supprime un rôle.
     */
    public function destroy(Role $role): JsonResponse
    {
        // Vérifier si le rôle est utilisé par des utilisateurs
        if ($role->users()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer ce rôle car il est assigné à des utilisateurs',
            ], 422);
        }

        $validated['deleted_by'] = $request->user()->id;
        $role->delete();

        return response()->json([
            'message' => 'Rôle supprimé avec succès',
        ]);
    }

    /**
     * Active ou désactive un rôle.
     */
    public function toggleActive(Request $request, Role $role): JsonResponse
    {
        $role->update(['is_active' => !$role->is_active]);

        return response()->json([
            'data' => $role,
            'message' => $role->is_active 
                ? 'Rôle activé avec succès'
                : 'Rôle désactivé avec succès',
        ]);
    }

    /**
     * Ajoute des permissions à un rôle.
     */
    public function attachPermissions(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->permissions()->syncWithoutDetaching($validated['permissions']);

        return response()->json([
            'data' => $role->load('permissions'),
            'message' => 'Permissions ajoutées avec succès',
        ]);
    }

    /**
     * Retire des permissions d'un rôle.
     */
    public function detachPermissions(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->permissions()->detach($validated['permissions']);

        return response()->json([
            'data' => $role->load('permissions'),
            'message' => 'Permissions retirées avec succès',
        ]);
    }

    /**
     * Liste toutes les permissions disponibles.
     */
    public function permissions(Request $request): JsonResponse
    {
        $query = Permission::query();

        // Filtres
        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        if ($request->filled('group')) {
            $query->byGroup($request->input('group'));
        }

        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        $permissions = $query->orderBy('group')
            ->orderBy('name')
            ->paginate($request->input('per_page', 50));

        return response()->json([
            'data' => $permissions->items(),
            'meta' => [
                'current_page' => $permissions->currentPage(),
                'last_page' => $permissions->lastPage(),
                'per_page' => $permissions->perPage(),
                'total' => $permissions->total(),
            ],
        ]);
    }

    /**
     * Retourne la liste des rôles pour les formulaires.
     */
    public function liste(): JsonResponse
    {
        $roles = Role::active()
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        return response()->json([
            'data' => $roles,
        ]);
    }
}
