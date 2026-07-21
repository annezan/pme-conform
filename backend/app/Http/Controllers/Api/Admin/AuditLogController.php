<?php

/**
 * Controleur AuditLogController — Consultation du journal d'audit.
 */

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(
    path: "/api/admin/audit-logs",
    get: new OA\Get(
        operationId: "admin-audit-logs-index",
        summary: "Lister le journal d'audit",
        description: "Consultation du journal d'audit avec filtres avancés",
        tags: ["Administration"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "categorie",
                in: "query",
                description: "Filtrer par catégorie",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["auth", "mission", "document", "client", "analyse"])
            ),
            new OA\Parameter(
                name: "user_id",
                in: "query",
                description: "Filtrer par utilisateur",
                required: false,
                schema: new OA\Schema(type: "integer")
            ),
            new OA\Parameter(
                name: "action",
                in: "query",
                description: "Rechercher dans l'action",
                required: false,
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "date_debut",
                in: "query",
                description: "Date de début (YYYY-MM-DD)",
                required: false,
                schema: new OA\Schema(type: "string", format: "date")
            ),
            new OA\Parameter(
                name: "date_fin",
                in: "query",
                description: "Date de fin (YYYY-MM-DD)",
                required: false,
                schema: new OA\Schema(type: "string", format: "date")
            ),
            new OA\Parameter(
                name: "page",
                in: "query",
                description: "Page",
                required: false,
                schema: new OA\Schema(type: "integer", default: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Journal d'audit paginé",
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
                                    new OA\Property(property: "categorie", type: "string", example: "mission"),
                                    new OA\Property(property: "action", type: "string", example: "Création de la mission"),
                                    new OA\Property(property: "details", type: "object", nullable: true),
                                    new OA\Property(property: "ip_address", type: "string", nullable: true),
                                    new OA\Property(property: "user_agent", type: "string", nullable: true),
                                    new OA\Property(property: "created_at", type: "string", format: "date-time"),
                                    new OA\Property(property: "user", type: "object", properties: [
                                        new OA\Property(property: "id", type: "integer"),
                                        new OA\Property(property: "nom", type: "string"),
                                        new OA\Property(property: "prenom", type: "string")
                                    ])
                                ]
                            )
                        ),
                        new OA\Property(property: "current_page", type: "integer"),
                        new OA\Property(property: "total", type: "integer"),
                        new OA\Property(property: "per_page", type: "integer")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé (admin requis)")
        ]
    )
)]

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('user:id,nom,prenom');

        if ($request->filled('categorie')) {
            $query->where('categorie', $request->categorie);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('action')) {
            $query->where('action', 'ilike', "%{$request->action}%");
        }
        if ($request->filled('date_debut')) {
            $query->where('created_at', '>=', $request->date_debut);
        }
        if ($request->filled('date_fin')) {
            $query->where('created_at', '<=', $request->date_fin . ' 23:59:59');
        }

        return response()->json(
            $query->latest('created_at')->paginate(30)
        );
    }

    public function categories(): JsonResponse
    {
        $categories = AuditLog::select('categorie')
            ->distinct()
            ->whereNotNull('categorie')
            ->pluck('categorie');

        return response()->json(['categories' => $categories]);
    }
}
