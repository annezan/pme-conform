<?php

/**
 * Evenement AgentRequeteRecue — Emis avant l'appel au LLM.
 *
 * Les modules metier peuvent ecouter cet evenement pour :
 * - Ajouter du contexte supplementaire a la requete
 * - Rejeter la requete (ex: contenu interdit)
 * - Logger des metriques specifiques au module
 */

namespace App\Events;

use App\Models\Agent;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentRequeteRecue
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Agent $agent,
        public string $requete,
        public User $user,
        public ?Conversation $conversation = null,
    ) {}
}
