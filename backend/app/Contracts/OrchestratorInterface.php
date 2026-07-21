<?php

/**
 * Interface OrchestratorInterface — Contrat de l'orchestrateur IA.
 *
 * Point d'entree central pour toutes les interactions avec les agents.
 * Gere le pipeline complet : pseudonymisation, RAG, LLM, audit.
 */

namespace App\Contracts;

use App\Models\Agent;
use App\Models\Conversation;

interface OrchestratorInterface
{
    /**
     * Traite une requete utilisateur de maniere synchrone.
     *
     * @return array{message: \App\Models\Message, conversation: Conversation, sources: array}
     */
    public function traiter(
        Agent $agent,
        string $requete,
        ?Conversation $conversation = null,
        ?int $missionId = null,
    ): array;

    /**
     * Traite une requete avec streaming de la reponse.
     *
     * @return \Generator<string> Flux de tokens, puis retourne les metadonnees finales
     */
    public function traiterStream(
        Agent $agent,
        string $requete,
        ?Conversation $conversation = null,
        ?int $missionId = null,
    ): \Generator;
}
