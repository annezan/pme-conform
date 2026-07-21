<?php

/**
 * Service ExtractorFactory — Selectionne le bon extracteur selon le type MIME.
 */

namespace App\Services\Document;

use App\Contracts\DocumentExtractorInterface;
use RuntimeException;

class ExtractorFactory
{
    /** @var DocumentExtractorInterface[] */
    private array $extracteurs;

    public function __construct()
    {
        $this->extracteurs = [
            new PdfExtractor(),
            new DocxExtractor(),
            new PptxExtractor(),
            new SpreadsheetExtractor(),
            new TextExtractor(),
            new ImageExtractor(),
        ];
    }

    /**
     * Retourne l'extracteur adapte au type MIME donne.
     *
     * @throws RuntimeException Si le type n'est pas supporte
     */
    public function pourTypeMime(string $typeMime): DocumentExtractorInterface
    {
        foreach ($this->extracteurs as $extracteur) {
            if ($extracteur->supporte($typeMime)) {
                return $extracteur;
            }
        }

        throw new RuntimeException("Type de document non supporte : {$typeMime}");
    }

    /**
     * Extrait le texte d'un fichier en detectant automatiquement l'extracteur.
     */
    public function extraire(string $cheminFichier, string $typeMime): string
    {
        return $this->pourTypeMime($typeMime)->extraire($cheminFichier, $typeMime);
    }

    /**
     * Verifie si un type MIME est supporte.
     */
    public function supporte(string $typeMime): bool
    {
        foreach ($this->extracteurs as $extracteur) {
            if ($extracteur->supporte($typeMime)) {
                return true;
            }
        }

        return false;
    }
}
