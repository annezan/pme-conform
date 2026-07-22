<?php

/**
 * Service PdfExtractor — Extraction de texte depuis un fichier PDF.
 *
 * Deux etages :
 *   1. Couche texte via smalot/pdfparser (rapide, PDF "natifs").
 *   2. Repli OCR pour les PDF scannes (images sans couche texte) : chaque page
 *      est rendue en image via Ghostscript, puis lue par Tesseract.
 *
 * Des marqueurs [PAGE_BREAK:N] sont inseres entre les pages pour la pagination.
 *
 * Pre-requis du repli OCR (sinon seul l'etage 1 fonctionne) :
 *   - Ghostscript : apt install ghostscript (Linux) — cf. services.ghostscript.path
 *   - Tesseract   : apt install tesseract-ocr tesseract-ocr-fra — cf. services.tesseract
 */

namespace App\Services\Document;

use App\Contracts\DocumentExtractorInterface;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Smalot\PdfParser\Parser;
use thiagoalessio\TesseractOCR\TesseractOCR;

class PdfExtractor implements DocumentExtractorInterface
{
    public function extraire(string $cheminFichier, string $typeMime): string
    {
        // Etage 1 : couche texte (PDF natif).
        $texte = $this->extraireCoucheTexte($cheminFichier);
        if (mb_strlen(trim($texte)) > 0) {
            return trim($texte);
        }

        // Etage 2 : PDF sans texte (scanne) -> OCR page par page.
        Log::info("PDF sans couche texte ({$cheminFichier}) : bascule sur l'OCR.");

        return $this->extraireViaOcr($cheminFichier);
    }

    /**
     * Extraction rapide de la couche texte via smalot/pdfparser.
     * Renvoie une chaine vide si le PDF n'a pas de texte selectionnable.
     */
    private function extraireCoucheTexte(string $cheminFichier): string
    {
        try {
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
        } catch (\Throwable $e) {
            // Un PDF illisible par pdfparser (chiffre, malforme...) n'est pas
            // fatal : on laisse l'OCR tenter sa chance.
            Log::warning("Lecture couche texte PDF echouee ({$cheminFichier}) : " . $e->getMessage());

            return '';
        }
    }

    /**
     * Repli OCR : rend chaque page en PNG (Ghostscript) puis lit via Tesseract.
     */
    private function extraireViaOcr(string $cheminFichier): string
    {
        $dossierTemp = $this->rendreEnImages($cheminFichier);

        try {
            $images = glob($dossierTemp . DIRECTORY_SEPARATOR . 'page-*.png') ?: [];
            sort($images); // page-001, page-002, ... dans l'ordre

            if (empty($images)) {
                throw new RuntimeException('Aucune page image generee pour l\'OCR.');
            }

            $langues = config('services.tesseract.lang', 'fra+eng');
            $cheminTesseract = config('services.tesseract.path');

            $texte = '';
            foreach ($images as $numero => $image) {
                $ocr = new TesseractOCR($image);
                if (! empty($cheminTesseract)) {
                    $ocr->executable($cheminTesseract);
                }
                $ocr->lang(...explode('+', $langues));

                $contenuPage = trim($ocr->run());
                if ($contenuPage !== '') {
                    $texte .= $contenuPage;
                    $texte .= "\n[PAGE_BREAK:" . ($numero + 1) . "]\n";
                }
            }

            return trim($texte);
        } catch (\Throwable $e) {
            Log::error("OCR PDF echoue ({$cheminFichier}) : " . $e->getMessage());
            throw new RuntimeException(
                'Echec de l\'OCR du PDF scanne. Verifiez que Ghostscript '
                . '(services.ghostscript.path) et Tesseract (services.tesseract.path) sont installes.',
                0,
                $e,
            );
        } finally {
            $this->nettoyer($dossierTemp);
        }
    }

    /**
     * Convertit le PDF en une image PNG par page via Ghostscript.
     * Retourne le chemin du dossier temporaire contenant les images.
     */
    private function rendreEnImages(string $cheminFichier): string
    {
        $dossierTemp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pdf-ocr-' . uniqid('', true);
        if (! mkdir($dossierTemp, 0700, true) && ! is_dir($dossierTemp)) {
            throw new RuntimeException("Impossible de creer le dossier temporaire OCR : {$dossierTemp}");
        }

        $gs = config('services.ghostscript.path', 'gs');
        $dpi = (int) config('services.ghostscript.dpi', 300);
        $sortie = $dossierTemp . DIRECTORY_SEPARATOR . 'page-%03d.png';

        $commande = sprintf(
            '%s -sDEVICE=png16m -r%d -dNOPAUSE -dBATCH -dQUIET -sOutputFile=%s %s',
            escapeshellcmd($gs),
            $dpi,
            escapeshellarg($sortie),
            escapeshellarg($cheminFichier),
        );

        exec($commande . ' 2>&1', $retour, $codeSortie);

        if ($codeSortie !== 0) {
            $this->nettoyer($dossierTemp);
            throw new RuntimeException(
                "Ghostscript a echoue (code {$codeSortie}) : " . implode(' ', $retour)
                . '. Verifiez l\'installation de Ghostscript (services.ghostscript.path).',
            );
        }

        return $dossierTemp;
    }

    /** Supprime le dossier temporaire et son contenu. */
    private function nettoyer(string $dossier): void
    {
        if (! is_dir($dossier)) {
            return;
        }
        foreach (glob($dossier . DIRECTORY_SEPARATOR . '*') ?: [] as $fichier) {
            @unlink($fichier);
        }
        @rmdir($dossier);
    }

    public function supporte(string $typeMime): bool
    {
        return in_array($typeMime, [
            'application/pdf',
        ]);
    }
}
