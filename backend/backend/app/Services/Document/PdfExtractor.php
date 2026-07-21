<?php

/**
 * Service PdfExtractor — Extraction de texte depuis un fichier PDF.
 *
 * Utilise smalot/pdfparser. Insere des marqueurs [PAGE_BREAK:N]
 * entre les pages pour permettre le suivi de la pagination dans les chunks.
 */

namespace App\Services\Document;

use App\Contracts\DocumentExtractorInterface;
use Smalot\PdfParser\Parser;

class PdfExtractor implements DocumentExtractorInterface
{
    public function extraire(string $cheminFichier, string $typeMime): string
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($cheminFichier);
        $pages = $pdf->getPages();
        $texte = '';

        foreach ($pages as $index => $page) {
            $contenuPage = $page->getText();
            if (! empty(trim($contenuPage))) {
                $texte .= $contenuPage;
                $texte .= "\n[PAGE_BREAK:" . ($index + 1) . "]\n";
            }
        }

        return trim($texte);
    }

    public function supporte(string $typeMime): bool
    {
        return in_array($typeMime, [
            'application/pdf',
        ]);
    }
}
