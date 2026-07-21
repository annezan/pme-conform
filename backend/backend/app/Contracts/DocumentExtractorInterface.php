<?php

/**
 * Interface DocumentExtractorInterface — Contrat pour l'extraction de texte.
 *
 * Chaque format de document (PDF, DOCX) a son propre extracteur.
 */

namespace App\Contracts;

interface DocumentExtractorInterface
{
    /**
     * Extrait le texte brut d'un fichier.
     *
     * @param string $cheminFichier Chemin absolu du fichier
     * @param string $typeMime Type MIME du fichier
     * @return string Texte extrait
     */
    public function extraire(string $cheminFichier, string $typeMime): string;

    /**
     * Verifie si ce type MIME est supporte par l'extracteur.
     */
    public function supporte(string $typeMime): bool;
}
