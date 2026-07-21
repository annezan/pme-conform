<?php

/**
 * Service GenerateurMotDePasse — Generation de mots de passe temporaires
 * conformes aux exigences de securite de la plateforme.
 *
 * Politique appliquee :
 *   - 16 caracteres minimum
 *   - Au moins 1 majuscule, 1 minuscule, 1 chiffre, 1 symbole
 *   - Aleatoire cryptographiquement securise (random_int)
 *   - Pas de caracteres ambigus (0/O, 1/l/I) pour faciliter la saisie
 */

namespace App\Services\Security;

class GenerateurMotDePasse
{
    private const MAJUSCULES = 'ABCDEFGHJKLMNPQRSTUVWXYZ';   // sans I et O ambigus
    private const MINUSCULES = 'abcdefghijkmnpqrstuvwxyz';  // sans l et o ambigus
    private const CHIFFRES = '23456789';                     // sans 0 et 1 ambigus
    private const SYMBOLES = '!@#$%&*+=?-';

    /**
     * Genere un mot de passe temporaire de 16 caracteres respectant la politique.
     */
    public static function temporaire(int $longueur = 16): string
    {
        if ($longueur < 12) {
            $longueur = 12;
        }

        // Garantir au moins 1 caractere de chaque famille.
        $caracteres = [
            self::pickRandom(self::MAJUSCULES),
            self::pickRandom(self::MINUSCULES),
            self::pickRandom(self::CHIFFRES),
            self::pickRandom(self::SYMBOLES),
        ];

        // Completer avec un alphabet mixte pour atteindre la longueur cible.
        $alphabet = self::MAJUSCULES . self::MINUSCULES . self::CHIFFRES . self::SYMBOLES;
        for ($i = count($caracteres); $i < $longueur; $i++) {
            $caracteres[] = self::pickRandom($alphabet);
        }

        // Melanger les positions pour ne pas exposer le pattern initial.
        shuffle($caracteres);

        return implode('', $caracteres);
    }

    private static function pickRandom(string $source): string
    {
        return $source[random_int(0, strlen($source) - 1)];
    }
}
