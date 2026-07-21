<?php

/**
 * Controleur DocumentController — Liste et gestion des documents.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(
    path: "/api/documents",
    get: new OA\Get(
        operationId: "documents-index",
        summary: "Lister tous les documents",
        description: "Retourne la liste paginée des documents avec filtres optionnels",
        tags: ["Documents"],
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
                name: "type",
                in: "query",
                description: "Filtrer par type de document",
                required: false,
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "statut",
                in: "query",
                description: "Filtrer par statut (brouillon, valide, archive)",
                required: false,
                schema: new OA\Schema(type: "string", enum: ["brouillon", "valide", "archive"])
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
                description: "Liste des documents",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/Document")
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
    )
)]

#[OA\PathItem(
    path: "/api/documents/{id}",
    get: new OA\Get(
        operationId: "documents-show",
        summary: "Afficher un document",
        description: "Retourne les détails complets d'un document",
        tags: ["Documents"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID du document",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Détails du document",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "document", ref: "#/components/schemas/Document")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 404, description: "Document non trouvé")
        ]
    ),
    delete: new OA\Delete(
        operationId: "documents-destroy",
        summary: "Supprimer un document",
        description: "Supprime un document de manière irréversible",
        tags: ["Documents"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID du document",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Document supprimé avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Document supprimé.")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 404, description: "Document non trouvé")
        ]
    )
)]

class DocumentController extends Controller
{
    public function __construct(
        private AuditService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Document::with('mission:id,reference,titre', 'uploadeur:id,nom,prenom');

        if ($request->filled('mission_id')) {
            $query->where('mission_id', $request->mission_id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function show(Document $document): JsonResponse
    {
        $document->load('mission:id,reference,titre', 'uploadeur:id,nom,prenom');

        return response()->json(['document' => $document]);
    }

    public function destroy(Document $document): JsonResponse
    {
        $document->delete();

        return response()->json(['message' => 'Document supprime.']);
    }
}
