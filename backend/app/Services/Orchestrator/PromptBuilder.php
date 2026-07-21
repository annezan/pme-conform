<?php

/**
 * Service PromptBuilder — Construction du tableau de messages pour le LLM.
 *
 * Assemble le prompt systeme de l'agent, le contexte RAG,
 * l'historique de conversation et la requete utilisateur.
 */

namespace App\Services\Orchestrator;

use App\Models\Agent;

class PromptBuilder
{
    /**
     * Construit le tableau messages[] pret pour l'API LLM.
     *
     * @param Agent $agent Agent cible avec son prompt systeme
     * @param string $requetePseudonymisee Requete utilisateur pseudonymisee
     * @param array $historique Historique de conversation [{role, content}]
     * @param array $chunksRag Chunks RAG pertinents [{contenu, document_titre, page}]
     * @return array Messages formates pour le LLM
     */
    public function construire(
        Agent $agent,
        string $requetePseudonymisee,
        array $historique = [],
        array $chunksRag = [],
    ): array {
        $messages = [];

        // 1. Prompt systeme de l'agent
        $messages[] = [
            'role' => 'system',
            'content' => $agent->prompt_systeme,
        ];

        // 2. Contexte RAG (si des documents pertinents ont ete trouves)
        if (! empty($chunksRag)) {
            $contexteRag = $this->formaterContexteRag($chunksRag);
            $messages[] = [
                'role' => 'system',
                'content' => $contexteRag,
            ];
        }

        // 3. Historique de conversation (messages precedents)
        foreach ($historique as $message) {
            $messages[] = [
                'role' => $message['role'],
                'content' => $message['content'],
            ];
        }

        // 4. Requete utilisateur actuelle
        $messages[] = [
            'role' => 'user',
            'content' => $requetePseudonymisee,
        ];

        return $messages;
    }

    /**
     * Formate les chunks RAG en un message systeme contextualisé.
     */
    private function formaterContexteRag(array $chunks): string
    {
        $texte = "Voici des extraits de documents pertinents pour repondre a la question de l'utilisateur. "
            . "Base ta reponse sur ces extraits quand ils sont pertinents. "
            . "Cite le document source entre parentheses quand tu utilises une information.\n\n";

        foreach ($chunks as $index => $chunk) {
            $source = $chunk['document_titre'] ?? 'Document inconnu';
            $page = isset($chunk['page']) ? " (page {$chunk['page']})" : '';
            $texte .= "--- Extrait " . ($index + 1) . " [{$source}{$page}] ---\n";
            $texte .= $chunk['contenu'] . "\n\n";
        }

        return $texte;
    }
}
