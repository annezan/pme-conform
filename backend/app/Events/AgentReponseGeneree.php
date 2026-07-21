<?php

/**
 * Evenement AgentReponseGeneree — Emis apres la generation de la reponse LLM.
 *
 * Les modules metier peuvent ecouter cet evenement pour :
 * - Declencher des actions post-reponse (notifications, scoring)
 * - Analyser la reponse pour des metriques specifiques
 */

namespace App\Events;

use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentReponseGeneree
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Agent $agent,
        public Message $message,
        public Conversation $conversation,
    ) {}
}
