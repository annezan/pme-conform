<?php

/**
 * Controleur ModuleController — Gestion des modules (admin).
 *
 * Permet d'activer/desactiver des modules dynamiquement.
 */

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(
    path: "/api/admin/modules",
    get: new OA\Get(
        operationId: "admin-modules-index",
        summary: "Lister les modules",
        description: "Retourne la liste de tous les modules avec leur nombre d'agents",
        tags: ["Administration"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des modules",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "modules",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/Module")
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé (admin requis)")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/admin/modules/{id}",
    get: new OA\Get(
        operationId: "admin-modules-show",
        summary: "Afficher un module",
        description: "Retourne les détails complets d'un module avec ses agents",
        tags: ["Administration"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID du module",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Détails du module",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "module", ref: "#/components/schemas/Module")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé (admin requis)"),
            new OA\Response(response: 404, description: "Module non trouvé")
        ]
    ),
    put: new OA\Put(
        operationId: "admin-modules-update",
        summary: "Mettre à jour un module",
        description: "Met à jour les informations d'un module",
        tags: ["Administration"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID du module",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "nom", type: "string", maxLength: 255, nullable: true, example: "Module mis à jour"),
                    new OA\Property(property: "description", type: "string", nullable: true, example: "Description mise à jour"),
                    new OA\Property(property: "configuration", type: "object", nullable: true, example: "param1: value1"),
                    new OA\Property(property: "ordre_affichage", type: "integer", nullable: true, example: 2)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Module mis à jour avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "module", ref: "#/components/schemas/Module")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé (admin requis)"),
            new OA\Response(response: 404, description: "Module non trouvé"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/admin/modules/{id}/toggle",
    post: new OA\Post(
        operationId: "admin-modules-toggle",
        summary: "Activer/Désactiver un module",
        description: "Inverse l'état d'activation d'un module (sauf les modules du noyau)",
        tags: ["Administration"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID du module",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "État du module inversé avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "module", ref: "#/components/schemas/Module"),
                        new OA\Property(property: "message", type: "string", example: "Module Module RGPD activé.")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé (modules du noyau ne peuvent pas être désactivés)"),
            new OA\Response(response: 404, description: "Module non trouvé")
        ]
    )
)]

class ModuleController extends Controller
{
    public function __construct(
        private AuditService $audit,
    ) {}

    public function index(): JsonResponse
    {
        $modules = Module::withCount('agents')
            ->orderBy('ordre_affichage')
            ->get();

        return response()->json(['modules' => $modules]);
    }

    public function show(Module $module): JsonResponse
    {
        $module->load('agents:id,nom,slug,type,is_active,module_id');

        return response()->json(['module' => $module]);
    }

    public function toggleActive(Module $module): JsonResponse
    {
        if ($module->is_core) {
            return response()->json(['message' => 'Les modules du noyau ne peuvent pas etre desactives.'], 403);
        }

        $module->update([
            'is_active' => ! $module->is_active,
            'active_depuis' => $module->is_active ? null : now(),
        ]);

        $action = $module->is_active ? 'activation' : 'desactivation';
        $this->audit->modificationModule($module, $action);

        return response()->json([
            'module' => $module->fresh(),
            'message' => "Module {$module->nom} " . ($module->is_active ? 'active' : 'desactive') . '.',
        ]);
    }

    public function update(Request $request, Module $module): JsonResponse
    {
        $data = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'configuration' => 'nullable|array',
            'ordre_affichage' => 'sometimes|integer',
        ]);

        $module->update($data);

        return response()->json(['module' => $module->fresh()]);
    }
}
