<?php

/**
 * Contrôleur API LlmController — Point d'entrée API pour le LLM.
 *
 * Permet de vérifier la disponibilité d'Ollama et de lister les modèles.
 */

namespace App\Http\Controllers\Api;

use App\Contracts\LLMConnectorInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\PathItem(
    path: "/api/llm/statut",
    get: new OA\Get(
        operationId: "llm-statut",
        summary: "Vérifier le statut du LLM",
        description: "Vérifie la disponibilité du serveur Ollama et retourne les informations de configuration",
        tags: ["LLM"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "LLM disponible",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "disponible", type: "boolean", example: true),
                        new OA\Property(property: "modele_defaut", type: "string", example: "llama2"),
                        new OA\Property(property: "host", type: "string", example: "http://localhost:11434")
                    ]
                )
            ),
            new OA\Response(
                response: 503,
                description: "LLM indisponible",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "disponible", type: "boolean", example: false),
                        new OA\Property(property: "modele_defaut", type: "string", example: "llama2"),
                        new OA\Property(property: "host", type: "string", example: "http://localhost:11434")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/llm/modeles",
    get: new OA\Get(
        operationId: "llm-modeles",
        summary: "Lister les modèles LLM",
        description: "Retourne la liste des modèles disponibles sur le serveur Ollama",
        tags: ["LLM"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des modèles disponibles",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "modeles",
                            type: "array",
                            items: new OA\Items(
                                type: "object",
                                properties: [
                                    new OA\Property(property: "name", type: "string", example: "llama2"),
                                    new OA\Property(property: "size", type: "integer", example: 3825819597),
                                    new OA\Property(property: "modified_at", type: "string", format: "date-time"),
                                    new OA\Property(property: "digest", type: "string", example: "sha256:abc123")
                                ]
                            ),
                            example: [
                                ["name" => "llama2", "size" => 3825819597, "modified_at" => "2024-01-15T10:00:00Z", "digest" => "sha256:abc123"],
                                ["name" => "mistral", "size" => 4108912533, "modified_at" => "2024-01-10T15:30:00Z", "digest" => "sha256:def456"]
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 503, description: "LLM indisponible")
        ]
    )
)]

class LlmController extends Controller
{
    public function __construct(
        private LLMConnectorInterface $llm,
    ) {}

    /**
     * Vérifie la disponibilité du serveur Ollama.
     */
    public function statut(): JsonResponse
    {
        $disponible = $this->llm->estDisponible();

        return response()->json([
            'disponible' => $disponible,
            'modele_defaut' => config('services.ollama.model'),
            'host' => config('services.ollama.host'),
        ], $disponible ? 200 : 503);
    }

    /**
     * Liste les modèles disponibles sur Ollama.
     */
    public function modeles(): JsonResponse
    {
        return response()->json([
            'modeles' => $this->llm->listerModeles(),
        ]);
    }
}
