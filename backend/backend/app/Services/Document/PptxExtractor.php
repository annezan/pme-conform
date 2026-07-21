<?php

/**
 * Service PptxExtractor — Extraction de texte depuis un PowerPoint (.pptx, .ppt).
 *
 * Utilise phpoffice/phppresentation. On parcourt chaque slide et chaque shape
 * pour recolter le texte des titres, contenus, tables et zones de texte.
 * Les notes du presentateur sont egalement extraites (utiles dans une
 * presentation de conformite ou les commentaires explicatifs vivent souvent
 * dans les notes plutot que dans les slides visibles).
 */

namespace App\Services\Document;

use App\Contracts\DocumentExtractorInterface;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\Shape\Group;
use PhpOffice\PhpPresentation\Shape\RichText;
use PhpOffice\PhpPresentation\Shape\Table;

class PptxExtractor implements DocumentExtractorInterface
{
    public function extraire(string $cheminFichier, string $typeMime): string
    {
        // PhpPresentation detecte automatiquement PPTX vs PPT (binaire ancien)
        $presentation = IOFactory::load($cheminFichier);

        $texte = '';
        $numSlide = 0;

        foreach ($presentation->getAllSlides() as $slide) {
            $numSlide++;
            $texteSlide = "\n--- Slide {$numSlide} ---\n";
            $contenu = '';

            foreach ($slide->getShapeCollection() as $shape) {
                $contenu .= $this->extraireShape($shape);
            }

            // Notes du presentateur (parfois critiques pour la documentation
            // de conformite : explications de mesures, sources legales, etc.)
            $note = $slide->getNote();
            if ($note) {
                foreach ($note->getShapeCollection() as $shape) {
                    $texteNote = $this->extraireShape($shape);
                    if (trim($texteNote) !== '') {
                        $contenu .= "\n[Notes presentateur] " . trim($texteNote) . "\n";
                    }
                }
            }

            if (trim($contenu) !== '') {
                $texte .= $texteSlide . $contenu;
            }
        }

        return trim($texte);
    }

    public function supporte(string $typeMime): bool
    {
        return in_array($typeMime, [
            'application/vnd.openxmlformats-officedocument.presentationml.presentation', // .pptx
            'application/vnd.ms-powerpoint', // .ppt
        ], true);
    }

    /**
     * Extrait le texte d'une shape PowerPoint (texte, table, ou groupe imbrique).
     */
    private function extraireShape($shape): string
    {
        // Group : on descend recursivement dans les shapes filles
        if ($shape instanceof Group) {
            $texte = '';
            foreach ($shape->getShapeCollection() as $enfant) {
                $texte .= $this->extraireShape($enfant);
            }
            return $texte;
        }

        // RichText : titres, paragraphes, listes a puces
        if ($shape instanceof RichText) {
            $texte = '';
            foreach ($shape->getParagraphs() as $paragraphe) {
                foreach ($paragraphe->getRichTextElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $texte .= $element->getText();
                    }
                }
                $texte .= "\n";
            }
            return $texte;
        }

        // Table : on concatene les cellules ligne par ligne
        if ($shape instanceof Table) {
            $texte = '';
            foreach ($shape->getRows() as $ligne) {
                $cellules = [];
                foreach ($ligne->getCells() as $cellule) {
                    foreach ($cellule->getParagraphs() as $paragraphe) {
                        foreach ($paragraphe->getRichTextElements() as $element) {
                            if (method_exists($element, 'getText')) {
                                $cellules[] = $element->getText();
                            }
                        }
                    }
                }
                if (! empty($cellules)) {
                    $texte .= implode(' | ', $cellules) . "\n";
                }
            }
            return $texte;
        }

        return '';
    }
}
