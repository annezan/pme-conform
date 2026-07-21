<?php

/**
 * Controleur AgentAdminController — Administration des agents IA.
 *
 * Permet de modifier les prompts et la configuration des agents
 * sans redeploiement.
 */

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(
    path: "/api/admin/agents",
    get: new OA\Get(
        operationId: "admin-agents-index",
        summary: "Lister les agents IA (admin)",
        description: "Retourne la liste de tous les agents IA avec leurs modules pour l'administration",
        tags: ["Administration"],
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
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé (admin requis)")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/admin/agents/{id}",
    get: new OA\Get(
        operationId: "admin-agents-show",
        summary: "Afficher un agent IA (admin)",
        description: "Retourne les détails complets d'un agent IA pour l'administration",
        tags: ["Administration"],
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
                        new OA\Property(property: "agent", ref: "#/components/schemas/Agent")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé (admin requis)"),
            new OA\Response(response: 404, description: "Agent non trouvé")
        ]
    ),
    put: new OA\Put(
        operationId: "admin-agents-update",
        summary: "Mettre à jour un agent IA (admin)",
        description: "Met à jour la configuration et les paramètres d'un agent IA",
        tags: ["Administration"],
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
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "nom", type: "string", maxLength: 255, nullable: true, example: "Agent RGPD mis à jour"),
                    new OA\Property(property: "description", type: "string", nullable: true, example: "Description mise à jour"),
                    new OA\Property(property: "prompt_systeme", type: "string", nullable: true, example: "Tu es un expert RGPD..."),
                    new OA\Property(property: "temperature", type: "number", minimum: 0, maximum: 2, nullable: true, example: 0.7),
                    new OA\Property(property: "max_tokens", type: "integer", minimum: 100, maximum: 32000, nullable: true, example: 2048),
                    new OA\Property(property: "modele_llm", type: "string", maxLength: 100, nullable: true, example: "llama2"),
                    new OA\Property(property: "is_active", type: "boolean", nullable: true, example: true),
                    new OA\Property(property: "configuration", type: "object", nullable: true, example: "param1: value1")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Agent IA mis à jour avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "agent", ref: "#/components/schemas/Agent"),
                        new OA\Property(property: "message", type: "string", example: "Agent Agent RGPD mis à jour.")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Non autorisé (admin requis)"),
            new OA\Response(response: 404, description: "Agent non trouvé"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    )
)]

class AgentAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $agents = Agent::with('module:id,nom,slug')
            ->orderBy('ordre_affichage')
            ->get();

        return response()->json(['agents' => $agents]);
    }

    public function show(Agent $agent): JsonResponse
    {
        $agent->load('module:id,nom,slug');

        return response()->json(['agent' => $agent]);
    }

    public function update(Request $request, Agent $agent): JsonResponse
    {
        $data = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'prompt_systeme' => 'sometimes|string',
            'temperature' => 'sometimes|numeric|min:0|max:2',
            'max_tokens' => 'nullable|integer|min:100|max:32000',
            'modele_llm' => 'nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
            'configuration' => 'nullable|array',
        ]);

        $agent->update($data);

        return response()->json([
            'agent' => $agent->fresh()->load('module:id,nom,slug'),
            'message' => "Agent {$agent->nom} mis a jour.",
        ]);
    }
}
