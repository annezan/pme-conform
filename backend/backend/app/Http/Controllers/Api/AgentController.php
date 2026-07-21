<?php

/**
 * Controleur AgentController — API des agents IA.
 *
 * Liste les agents disponibles et retourne les details d'un agent.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\PathItem(
    path: "/api/agents",
    get: new OA\Get(
        operationId: "agents-index",
        summary: "Lister les agents IA",
        description: "Retourne la liste des agents IA actifs avec leurs modules",
        tags: ["Agents IA"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des agents IA",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "agents",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/Agent")
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/agents/{id}",
    get: new OA\Get(
        operationId: "agents-show",
        summary: "Afficher un agent IA",
        description: "Retourne les détails complets d'un agent IA avec son module et statistiques",
        tags: ["Agents IA"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID de l'agent",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Détails de l'agent IA",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "agent", ref: "#/components/schemas/Agent"),
                        new OA\Property(property: "module", type: "object", nullable: true, properties: [
                            new OA\Property(property: "id", type: "integer"),
                            new OA\Property(property: "nom", type: "string"),
                            new OA\Property(property: "slug", type: "string"),
                            new OA\Property(property: "couleur", type: "string", nullable: true)
                        ]),
                        new OA\Property(property: "conversations_count", type: "integer", example: 5)
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 404, description: "Agent non trouvé")
        ]
    )
)]

class AgentController extends Controller
{
    public function index(): JsonResponse
    {
        $agents = Agent::actifs()
            ->with('module:id,nom,slug,couleur')
            ->orderBy('ordre_affichage')
            ->get([
                'id', 'slug', 'nom', 'description', 'icone', 'couleur',
                'type', 'module_id', 'is_core',
            ]);

        return response()->json(['agents' => $agents]);
    }

    public function show(Agent $agent): JsonResponse
    {
        $agent->load('module:id,nom,slug,couleur');

        return response()->json([
            'agent' => $agent->only([
                'id', 'slug', 'nom', 'description', 'icone', 'couleur',
                'type', 'is_core', 'module_id',
            ]),
            'module' => $agent->module?->only(['id', 'nom', 'slug', 'couleur']),
            'conversations_count' => $agent->conversations()
                ->where('user_id', auth()->id())
                ->count(),
        ]);
    }
}
