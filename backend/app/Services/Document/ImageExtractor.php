<?php

/**
 * Service ImageExtractor — Extraction de texte depuis une image via Tesseract OCR.
 *
 * Utilise thiagoalessio/tesseract_ocr (wrapper PHP autour du binaire tesseract).
 * Le binaire Tesseract doit etre installe sur le serveur :
 *   - Windows : https://github.com/UB-Mannheim/tesseract/wiki
 *   - Linux   : apt install tesseract-ocr tesseract-ocr-fra
 *   - macOS   : brew install tesseract tesseract-lang
 *
 * Configuration .env (optionnelle) :
 *   - TESSERACT_PATH=C:/Program Files/Tesseract-OCR/tesseract.exe
 *   - TESSERACT_LANG=fra+eng
 */

namespace App\Services\Document;

use App\Contracts\DocumentExtractorInterface;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use thiagoalessio\TesseractOCR\TesseractOCR;

class ImageExtractor implements DocumentExtractorInterface
{
    /** Types MIME images couramment uploades par les clients. */
    private const TYPES_SUPPORTES = [
        'image/png',
        'image/jpeg',
        'image/jpg',
        'image/webp',
        'image/tiff',
        'image/bmp',
        'image/gif',
    ];

    public function extraire(string $cheminFichier, string $typeMime): string
    {
        if (! $this->supporte($typeMime)) {
            throw new RuntimeException("Type MIME image non supporte : {$typeMime}");
        }

        $ocr = new TesseractOCR($cheminFichier);

        // Chemin du binaire tesseract si non present dans le PATH.
        $cheminBinaire = config('services.tesseract.path');
        if (! empty($cheminBinaire)) {
            $ocr->executable($cheminBinaire);
        }

        // Langues OCR : francais + anglais par defaut (les fichiers de langue
        // doivent etre installes avec Tesseract).
        $langues = config('services.tesseract.lang', 'fra+eng');
        $ocr->lang(...explode('+', $langues));

        try {
            $texte = $ocr->run();
        } catch (\Throwable $e) {
            Log::error("OCR image echoue ({$cheminFichier}) : " . $e->getMessage());
            throw new RuntimeException(
                'Echec de l\'extraction OCR. Verifiez que Tesseract est installe et configure (services.tesseract.path).',
                0,
                $e,
            );
        }

        return trim($texte);
    }

    public function supporte(string $typeMime): bool
    {
        return in_array(strtolower($typeMime), self::TYPES_SUPPORTES, true);
    }
}
