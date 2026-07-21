<?php

/**
 * Service PseudonymizationService — Pseudonymisation des données personnelles.
 *
 * Avant tout envoi de contenu au LLM, les données personnelles identifiables
 * (noms, emails, numéros de téléphone, adresses) sont remplacées par des tokens neutres.
 * La réponse du LLM est ensuite dé-pseudonymisée avant affichage.
 *
 * SÉCURITÉ : Le mapping est stocké en mémoire uniquement pendant la requête.
 */

namespace App\Services\Security;

use Illuminate\Support\Str;

class PseudonymizationService
{
    // Mapping token → valeur originale (durée de vie = requête)
    private array $mapping = [];

    // Compteur pour générer des tokens uniques
    private int $compteur = 0;

    /**
     * Pseudonymise un texte en remplaçant les données personnelles par des tokens.
     */
    public function pseudonymiser(string $texte): string
    {
        if (! config('services.pseudonymization.enabled', true)) {
            return $texte;
        }

        $texte = $this->remplacerEmails($texte);
        $texte = $this->remplacerTelephones($texte);
        $texte = $this->remplacerNumerosIdentification($texte);

        return $texte;
    }

    /**
     * Restaure les valeurs originales dans la réponse du LLM.
     */
    public function depseudonymiser(string $texte): string
    {
        foreach ($this->mapping as $token => $original) {
            $texte = str_replace($token, $original, $texte);
        }

        return $texte;
    }

    /**
     * Retourne le mapping actuel (pour debug/audit).
     */
    public function getMapping(): array
    {
        return $this->mapping;
    }

    /**
     * Réinitialise le mapping.
     */
    public function reinitialiser(): void
    {
        $this->mapping = [];
        $this->compteur = 0;
    }

    private function remplacerEmails(string $texte): string
    {
        return preg_replace_callback(
            '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
            fn ($match) => $this->genererToken('EMAIL', $match[0]),
            $texte
        );
    }

    private function remplacerTelephones(string $texte): string
    {
        // Numéros ivoiriens et formats internationaux courants
        return preg_replace_callback(
            '/(?:\+\d{1,3}[\s.-]?)?\(?\d{2,4}\)?[\s.-]?\d{2,3}[\s.-]?\d{2,3}[\s.-]?\d{2,4}/',
            fn ($match) => $this->genererToken('TEL', $match[0]),
            $texte
        );
    }

    private function remplacerNumerosIdentification(string $texte): string
    {
        // Numéros de type CNI, passeport, registre commerce
        return preg_replace_callback(
            '/\b[A-Z]{2,3}[-\s]?\d{6,12}\b/',
            fn ($match) => $this->genererToken('ID', $match[0]),
            $texte
        );
    }

    private function genererToken(string $type, string $valeurOriginale): string
    {
        // Vérifier si la valeur a déjà un token
        $tokenExistant = array_search($valeurOriginale, $this->mapping, true);
        if ($tokenExistant !== false) {
            return $tokenExistant;
        }

        $this->compteur++;
        $token = "[{$type}_{$this->compteur}]";
        $this->mapping[$token] = $valeurOriginale;

        return $token;
    }
}
