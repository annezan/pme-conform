<?php

/**
 * Service DocxExtractor — Extraction de texte depuis un fichier Word (DOCX).
 *
 * Utilise phpoffice/phpword pour parcourir les sections et elements du document.
 */

namespace App\Services\Document;

use App\Contracts\DocumentExtractorInterface;
use PhpOffice\PhpWord\IOFactory;

class DocxExtractor implements DocumentExtractorInterface
{
    public function extraire(string $cheminFichier, string $typeMime): string
    {
        $phpWord = IOFactory::load($cheminFichier);
        $texte = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $texte .= $this->extraireElement($element);
            }
        }

        return trim($texte);
    }

    public function supporte(string $typeMime): bool
    {
        return in_array($typeMime, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
        ]);
    }

    private function extraireElement($element): string
    {
        $texte = '';

        if (method_exists($element, 'getText')) {
            $texte .= $element->getText() . "\n";
        } elseif (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child) {
                $texte .= $this->extraireElement($child);
            }
        }

        return $texte;
    }
}
