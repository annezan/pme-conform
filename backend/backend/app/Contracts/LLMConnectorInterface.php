<?php

/**
 * Interface LLMConnectorInterface — Contrat pour les connecteurs LLM.
 *
 * Permet de découpler le noyau du fournisseur LLM spécifique.
 * Actuellement implémenté par OllamaConnector.
 */

namespace App\Contracts;

interface LLMConnectorInterface
{
    /**
     * Envoie une requête de complétion au LLM.
     *
     * @param array $messages  Historique de conversation [{role, content}]
     * @param string|null $modele  Modèle à utiliser (null = défaut)
     * @param float $temperature  Créativité (0.0 = déterministe, 1.0 = créatif)
     * @param int|null $maxTokens  Limite de tokens en sortie
     * @return array{content: string, tokens_entree: int, tokens_sortie: int, duree_ms: int, modele: string}
     */
    public function completer(
        array $messages,
        ?string $modele = null,
        float $temperature = 0.7,
        ?int $maxTokens = null,
    ): array;

    /**
     * Envoie une requête de complétion avec streaming.
     *
     * @return \Generator<string>  Flux de tokens
     */
    public function completerStream(
        array $messages,
        ?string $modele = null,
        float $temperature = 0.7,
        ?int $maxTokens = null,
    ): \Generator;

    /**
     * Génère un embedding vectoriel pour un texte.
     *
     * @return array<float>  Vecteur d'embedding
     */
    public function genererEmbedding(string $texte, ?string $modele = null): array;

    /**
     * Vérifie la disponibilité du serveur LLM.
     */
    public function estDisponible(): bool;

    /**
     * Liste les modèles disponibles sur le serveur.
     */
    public function listerModeles(): array;
}
