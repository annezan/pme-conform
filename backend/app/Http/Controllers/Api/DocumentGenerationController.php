<?php

/**
 * Controleur DocumentGenerationController — Generation de documents via LLM.
 *
 * Utilise l'orchestrateur pour generer du contenu a partir d'un agent de type generateur.
 * Le contenu genere est sauvegarde comme message dans une conversation (audit trail).
 */

namespace App\Http\Controllers\Api;

use App\Contracts\OrchestratorInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateDocumentRequest;
use App\Models\Agent;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

#[OA\PathItem(
    path: "/api/documents/generate",
    post: new OA\Post(
        operationId: "documents-generate",
        summary: "Générer un document via IA",
        description: "Génère un document personnalisé en utilisant un agent IA de type générateur",
        tags: ["Documents"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["agent_id", "type_document", "contexte"],
                properties: [
                    new OA\Property(property: "agent_id", type: "integer", example: 1),
                    new OA\Property(property: "type_document", type: "string", enum: ["rapport_audit", "politique", "registre", "aipd", "courrier_artci", "charte", "autre"], example: "rapport_audit"),
                    new OA\Property(property: "contexte", type: "string", example: "Entreprise de services informatiques traitant des données clients"),
                    new OA\Property(property: "mission_id", type: "integer", nullable: true, example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Document généré avec succès",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "contenu", type: "string", example: "# Rapport d'Audit\n\n## Introduction..."),
                        new OA\Property(property: "conversation_id", type: "integer", example: 123),
                        new OA\Property(property: "message_id", type: "integer", example: 456),
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
    path: "/api/messages/{message_id}/download",
    get: new OA\Get(
        operationId: "messages-download",
        summary: "Télécharger un message généré",
        description: "Télécharge le contenu d'un message généré au format Markdown",
        tags: ["Documents"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(
                name: "message_id",
                in: "path",
                description: "ID du message",
                required: true,
                schema: new OA\Schema(type: "integer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Fichier Markdown téléchargé",
                content: new OA\MediaType(
                    mediaType: "text/markdown",
                    schema: new OA\Schema(type: "string", format: "binary")
                ),
                headers: [
                    new OA\Header(
                        header: "Content-Disposition",
                        description: "Nom du fichier",
                        schema: new OA\Schema(type: "string", example: "attachment; filename=\"document-genere-456.md\"")
                    )
                ]
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Accès non autorisé"),
            new OA\Response(response: 404, description: "Message non trouvé")
        ]
    )
)]

class DocumentGenerationController extends Controller
{
    public function __construct(
        private OrchestratorInterface $orchestrator,
    ) {}

    /**
     * Genere un document via un agent generateur.
     */
    public function generate(GenerateDocumentRequest $request): JsonResponse
    {
        $agent = Agent::findOrFail($request->agent_id);

        // Construire la requete de generation
        $requete = $this->construireRequete(
            $request->type_document,
            $request->contexte,
        );

        $resultat = $this->orchestrator->traiter(
            agent: $agent,
            requete: $requete,
            missionId: $request->mission_id,
        );

        return response()->json([
            'contenu' => $resultat['message']->contenu,
            'conversation_id' => $resultat['conversation']->id,
            'message_id' => $resultat['message']->id,
            'sources' => $resultat['sources'],
        ], 201);
    }

    /**
     * Telecharge le contenu genere en format texte.
     */
    public function download(Message $message): Response
    {
        // Verifier que l'utilisateur a acces a cette conversation
        $conversation = $message->conversation;
        if ($conversation->user_id !== auth()->id()) {
            abort(403, 'Acces non autorise.');
        }

        $nomFichier = 'document-genere-' . $message->id . '.md';

        return response($message->contenu, 200, [
            'Content-Type' => 'text/markdown',
            'Content-Disposition' => "attachment; filename=\"{$nomFichier}\"",
        ]);
    }

    /**
     * Construit la requete de generation a partir du type et du contexte.
     */
    private function construireRequete(string $typeDocument, string $contexte): string
    {
        $labels = [
            'rapport_audit' => 'un rapport d\'audit de conformite',
            'politique' => 'une politique de confidentialite',
            'registre' => 'un registre des activites de traitement',
            'aipd' => 'une analyse d\'impact sur la protection des donnees (AIPD)',
            'courrier_artci' => 'un courrier officiel a destination de l\'ARTCI',
            'charte' => 'une charte informatique',
            'autre' => 'un document',
        ];

        $label = $labels[$typeDocument] ?? 'un document';

        return "Genere {$label} en te basant sur le contexte suivant :\n\n{$contexte}";
    }
}
