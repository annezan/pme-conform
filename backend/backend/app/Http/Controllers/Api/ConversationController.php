<?php

/**
 * Controleur ConversationController — API de chat avec les agents IA.
 *
 * Gere la creation de conversations, l'envoi de messages (sync et streaming),
 * et la consultation de l'historique.
 */

namespace App\Http\Controllers\Api;

use App\Contracts\OrchestratorInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Http\Requests\StoreConversationRequest;
use App\Models\Agent;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[OA\PathItem(
    path: "/api/conversations",
    get: new OA\Get(
        operationId: "conversations-index",
        summary: "Lister les conversations",
        description: "Retourne la liste paginée des conversations de l'utilisateur connecté",
        tags: ["Conversations"],
        security: [["bearerAuth" => []]],
        parameters: [
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
                description: "Liste des conversations",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(ref: "#/components/schemas/Conversation")
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
        operationId: "conversations-store",
        summary: "Démarrer une nouvelle conversation",
        description: "Crée une nouvelle conversation avec un agent IA et envoie le premier message",
        tags: ["Conversations"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["agent_id", "message"],
                properties: [
                    new OA\Property(property: "agent_id", type: "integer", example: 1),
                    new OA\Property(property: "message", type: "string", example: "Bonjour, j'ai besoin d'aide pour une analyse RGPD"),
                    new OA\Property(property: "mission_id", type: "integer", nullable: true, example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Conversation créée avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "conversation", ref: "#/components/schemas/Conversation"),
                        new OA\Property(property: "message", type: "string", example: "Réponse de l'agent IA"),
                        new OA\Property(property: "sources", type: "array", items: new OA\Items(type: "string"), nullable: true)
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/conversations/{id}",
    get: new OA\Get(
        operationId: "conversations-show",
        summary: "Afficher une conversation",
        description: "Retourne les détails complets d'une conversation avec tous ses messages",
        tags: ["Conversations"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID de la conversation",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Détails de la conversation",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "conversation", ref: "#/components/schemas/Conversation")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Accès non autorisé"),
            new OA\Response(response: 404, description: "Conversation non trouvée")
        ]
    ),
    post: new OA\Post(
        operationId: "conversations-message",
        summary: "Envoyer un message",
        description: "Envoie un message dans une conversation existante et obtient la réponse de l'agent",
        tags: ["Conversations"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                description: "ID de la conversation",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["message"],
                properties: [
                    new OA\Property(property: "message", type: "string", example: "Pouvez-vous m'expliquer les exigences de l'article 32 du RGPD ?")
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Message envoyé et réponse reçue",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Réponse de l'agent IA"),
                        new OA\Property(property: "sources", type: "array", items: new OA\Items(type: "string"), nullable: true)
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Accès non autorisé"),
            new OA\Response(response: 404, description: "Conversation non trouvée"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    )
)]

class ConversationController extends Controller
{
    public function __construct(
        private OrchestratorInterface $orchestrator,
    ) {}

    /**
     * Liste les conversations de l'utilisateur connecte.
     */
    public function index(): JsonResponse
    {
        $conversations = Conversation::where('user_id', auth()->id())
            ->with('agent:id,nom,slug,icone,couleur')
            ->where('statut', 'active')
            ->latest()
            ->paginate(20);

        return response()->json($conversations);
    }

    /**
     * Demarre une nouvelle conversation avec un agent.
     */
    public function store(StoreConversationRequest $request): JsonResponse
    {
        $agent = Agent::findOrFail($request->agent_id);

        $resultat = $this->orchestrator->traiter(
            agent: $agent,
            requete: $request->message,
            missionId: $request->mission_id,
        );

        return response()->json([
            'conversation' => $resultat['conversation']->load('agent:id,nom,slug,icone,couleur'),
            'message' => $resultat['message'],
            'sources' => $resultat['sources'],
        ], 201);
    }

    /**
     * Affiche une conversation avec ses messages.
     */
    public function show(Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== auth()->id()) {
            abort(403, 'Acces non autorise.');
        }

        $conversation->load([
            'agent:id,nom,slug,icone,couleur',
            'messages' => fn ($q) => $q->orderBy('created_at'),
        ]);

        return response()->json(['conversation' => $conversation]);
    }

    /**
     * Envoie un message dans une conversation existante (synchrone).
     */
    public function message(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $resultat = $this->orchestrator->traiter(
            agent: $conversation->agent,
            requete: $request->message,
            conversation: $conversation,
        );

        return response()->json([
            'message' => $resultat['message'],
            'sources' => $resultat['sources'],
        ]);
    }

    /**
     * Envoie un message avec streaming de la reponse (SSE).
     */
    public function stream(SendMessageRequest $request, Conversation $conversation): StreamedResponse
    {
        $agent = $conversation->agent;
        $requete = $request->message;
        $orchestrator = $this->orchestrator;

        return new StreamedResponse(function () use ($orchestrator, $agent, $requete, $conversation) {
            $generator = $orchestrator->traiterStream(
                agent: $agent,
                requete: $requete,
                conversation: $conversation,
            );

            foreach ($generator as $token) {
                echo "data: " . json_encode(['token' => $token]) . "\n\n";
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }

            // Metadonnees finales (return value du Generator)
            $meta = $generator->getReturn();
            echo "data: " . json_encode([
                'done' => true,
                'message_id' => $meta['message_id'] ?? null,
                'conversation_id' => $meta['conversation_id'] ?? null,
                'sources' => $meta['sources'] ?? [],
                'duree_ms' => $meta['duree_ms'] ?? null,
            ]) . "\n\n";

            if (ob_get_level()) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
