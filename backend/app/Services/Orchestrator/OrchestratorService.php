<?php

/**
 * Service OrchestratorService — Cerveau central de la plateforme ASC-IA.
 *
 * Pipeline complet pour chaque requete utilisateur :
 * 1. Evenement AgentRequeteRecue
 * 2. Creer/recuperer la conversation
 * 3. Sauver le message utilisateur
 * 4. Pseudonymiser la requete
 * 5. Recherche RAG (si agent analytique)
 * 6. Construire le prompt
 * 7. Appeler le LLM (synchrone ou streaming)
 * 8. Depseudonymiser la reponse
 * 9. Sauver le message assistant
 * 10. Evenement AgentReponseGeneree + Audit
 */

namespace App\Services\Orchestrator;

use App\Contracts\LLMConnectorInterface;
use App\Contracts\OrchestratorInterface;
use App\Contracts\RetrievalInterface;
use App\Events\AgentReponseGeneree;
use App\Events\AgentRequeteRecue;
use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Audit\AuditService;
use App\Services\Security\PseudonymizationService;

class OrchestratorService implements OrchestratorInterface
{
    public function __construct(
        private LLMConnectorInterface $llm,
        private PseudonymizationService $pseudonymisation,
        private AuditService $audit,
        private RetrievalInterface $retrieval,
        private PromptBuilder $promptBuilder,
    ) {}

    public function traiter(
        Agent $agent,
        string $requete,
        ?Conversation $conversation = null,
        ?int $missionId = null,
    ): array {
        $user = auth()->user();

        // 1. Evenement avant traitement
        AgentRequeteRecue::dispatch($agent, $requete, $user, $conversation);

        // 2. Creer ou recuperer la conversation
        $conversation = $this->obtenirConversation($agent, $conversation, $missionId);

        // 3. Sauver le message utilisateur
        $messageUser = $this->sauverMessage($conversation, 'user', $requete);

        // 4. Pseudonymiser
        $requetePseudo = $this->pseudonymisation->pseudonymiser($requete);

        // 5. Recherche RAG si applicable
        $sources = [];
        $chunksRag = [];
        if ($this->doitUtiliserRag($agent)) {
            $chunksRag = $this->retrieval->rechercher($requete, $missionId);
            $sources = array_map(fn ($c) => [
                'document_id' => $c['document_id'],
                'document_titre' => $c['document_titre'],
                'page' => $c['page'],
                'score' => $c['score'],
            ], $chunksRag);
        }

        // 6. Construire le prompt
        $historique = $conversation->getHistoriquePourLlm(20);
        // Retirer le dernier message (c'est celui qu'on vient de sauver)
        array_pop($historique);

        $messages = $this->promptBuilder->construire(
            $agent,
            $requetePseudo,
            $historique,
            $chunksRag,
        );

        // 7. Appeler le LLM
        $resultat = $this->llm->completer(
            $messages,
            $agent->modele_llm_effectif,
            $agent->temperature,
            $agent->max_tokens_effectif,
        );

        // 8. Depseudonymiser la reponse
        $reponse = $this->pseudonymisation->depseudonymiser($resultat['content']);

        // 9. Sauver le message assistant
        $messageAssistant = $this->sauverMessage($conversation, 'assistant', $reponse, [
            'contenu_pseudonymise' => $resultat['content'],
            'tokens_entree' => $resultat['tokens_entree'],
            'tokens_sortie' => $resultat['tokens_sortie'],
            'duree_ms' => $resultat['duree_ms'],
            'sources' => ! empty($sources) ? $sources : null,
        ]);

        // 10. Evenement et audit
        AgentReponseGeneree::dispatch($agent, $messageAssistant, $conversation);
        $this->audit->requeteAgent($conversation, $agent->nom);

        return [
            'message' => $messageAssistant,
            'conversation' => $conversation,
            'sources' => $sources,
        ];
    }

    public function traiterStream(
        Agent $agent,
        string $requete,
        ?Conversation $conversation = null,
        ?int $missionId = null,
    ): \Generator {
        $user = auth()->user();

        // Etapes 1-6 identiques au mode synchrone
        AgentRequeteRecue::dispatch($agent, $requete, $user, $conversation);
        $conversation = $this->obtenirConversation($agent, $conversation, $missionId);
        $this->sauverMessage($conversation, 'user', $requete);

        $requetePseudo = $this->pseudonymisation->pseudonymiser($requete);

        $sources = [];
        $chunksRag = [];
        if ($this->doitUtiliserRag($agent)) {
            $chunksRag = $this->retrieval->rechercher($requete, $missionId);
            $sources = array_map(fn ($c) => [
                'document_id' => $c['document_id'],
                'document_titre' => $c['document_titre'],
                'page' => $c['page'],
                'score' => $c['score'],
            ], $chunksRag);
        }

        $historique = $conversation->getHistoriquePourLlm(20);
        array_pop($historique);

        $messages = $this->promptBuilder->construire($agent, $requetePseudo, $historique, $chunksRag);

        // 7. Streamer la reponse du LLM
        $reponsePseudo = '';
        $debut = microtime(true);

        foreach ($this->llm->completerStream($messages, $agent->modele_llm_effectif, $agent->temperature, $agent->max_tokens_effectif) as $token) {
            $reponsePseudo .= $token;
            // Depseudonymiser le token au fur et a mesure
            $tokenDepseudo = $this->pseudonymisation->depseudonymiser($token);
            yield $tokenDepseudo;
        }

        $dureeMs = (int) ((microtime(true) - $debut) * 1000);

        // 8-10. Post-streaming : sauver et notifier
        $reponse = $this->pseudonymisation->depseudonymiser($reponsePseudo);

        $messageAssistant = $this->sauverMessage($conversation, 'assistant', $reponse, [
            'contenu_pseudonymise' => $reponsePseudo,
            'duree_ms' => $dureeMs,
            'sources' => ! empty($sources) ? $sources : null,
        ]);

        AgentReponseGeneree::dispatch($agent, $messageAssistant, $conversation);
        $this->audit->requeteAgent($conversation, $agent->nom);

        // Retourner les metadonnees finales via le return du Generator
        return [
            'message_id' => $messageAssistant->id,
            'conversation_id' => $conversation->id,
            'sources' => $sources,
            'duree_ms' => $dureeMs,
        ];
    }

    /**
     * Determine si l'agent doit utiliser le RAG.
     */
    private function doitUtiliserRag(Agent $agent): bool
    {
        if ($agent->type === 'analytique') {
            return true;
        }

        $config = $agent->configuration ?? [];

        return ! empty($config['rag_enabled']);
    }

    /**
     * Cree une nouvelle conversation ou retourne l'existante.
     */
    private function obtenirConversation(Agent $agent, ?Conversation $conversation, ?int $missionId): Conversation
    {
        if ($conversation) {
            return $conversation;
        }

        return Conversation::create([
            'user_id' => auth()->id(),
            'agent_id' => $agent->id,
            'mission_id' => $missionId,
            'statut' => 'active',
        ]);
    }

    /**
     * Sauvegarde un message dans la conversation.
     */
    private function sauverMessage(
        Conversation $conversation,
        string $role,
        string $contenu,
        array $extras = [],
    ): Message {
        return Message::create(array_merge([
            'conversation_id' => $conversation->id,
            'role' => $role,
            'contenu' => $contenu,
        ], $extras));
    }
}
