<?php

/**
 * Service TextExtractor — Lecture directe de fichiers texte (.txt, .md, .rtf
 * partiellement, et fallback pour text/plain envoye par certains navigateurs
 * sur des CSV ou logs).
 *
 * Le fichier est lu en UTF-8 ; si l'encodage detecte est different
 * (Windows-1252, Latin-1), on convertit pour eviter les caracteres parasites
 * dans le prompt LLM.
 */

namespace App\Services\Document;

use App\Contracts\DocumentExtractorInterface;

class TextExtractor implements DocumentExtractorInterface
{
    public function extraire(string $cheminFichier, string $typeMime): string
    {
        $contenu = @file_get_contents($cheminFichier);
        if ($contenu === false) {
            return '';
        }

        // Detection d'encodage : la plupart des fichiers texte vient en UTF-8,
        // mais Windows / Office produisent souvent du Windows-1252 ou ISO-8859-1.
        // mb_detect_encoding n'est pas parfait, mais suffit pour eviter les ?.
        $encodage = mb_detect_encoding($contenu, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ASCII'], true);
        if ($encodage && $encodage !== 'UTF-8') {
            $contenu = mb_convert_encoding($contenu, 'UTF-8', $encodage);
        }

        // RTF : on degage les codes de formatage les plus courants pour avoir
        // un texte exploitable par le LLM (extraction grossiere ; pour du RTF
        // complexe, un parseur dedie serait mieux, mais le RTF est marginal).
        if (str_starts_with(trim($contenu), '{\\rtf')) {
            $contenu = preg_replace('/\\\\[a-z]+-?\d*\s?/', ' ', $contenu);
            $contenu = preg_replace('/[{}]/', '', $contenu);
        }

        return trim($contenu);
    }

    public function supporte(string $typeMime): bool
    {
        return in_array($typeMime, [
            'text/plain',
            'text/markdown',
            'text/x-markdown',
            'application/rtf',
            'text/rtf',
        ], true);
    }
}
