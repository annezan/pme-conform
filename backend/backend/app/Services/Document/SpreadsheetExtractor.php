<?php

/**
 * Service SpreadsheetExtractor — Extraction de texte depuis un classeur
 * (.xlsx, .xls, .ods, .csv).
 *
 * Utilise phpoffice/phpspreadsheet. Les cellules sont concatenees ligne par
 * ligne en valeurs separees par tab. On lit toutes les feuilles ; le titre
 * de chaque feuille est inclus en en-tete pour donner du contexte au LLM
 * (ex : "--- Feuille : Registre traitements ---").
 *
 * Limite : on borne le nombre de lignes par feuille pour eviter qu'un classeur
 * de 50000 lignes ne sature le prompt. Les fichiers tres volumineux donnent
 * souvent peu de contexte pertinent au LLM, mieux vaut tronquer.
 */

namespace App\Services\Document;

use App\Contracts\DocumentExtractorInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SpreadsheetExtractor implements DocumentExtractorInterface
{
    /** Plafond de lignes lues par feuille (au-dela : tronque). */
    private const MAX_LIGNES_PAR_FEUILLE = 500;

    public function extraire(string $cheminFichier, string $typeMime): string
    {
        // Detection auto via PhpSpreadsheet (XLSX / XLS / ODS / CSV)
        $classeur = IOFactory::load($cheminFichier);
        $texte = '';

        foreach ($classeur->getAllSheets() as $feuille) {
            $titre = $feuille->getTitle();
            $texte .= "\n--- Feuille : {$titre} ---\n";

            $lignes = $feuille->toArray(null, true, true, false);
            $nbLignes = 0;

            foreach ($lignes as $ligne) {
                // Ignore les lignes entierement vides
                $cellulesNonVides = array_filter($ligne, fn ($v) => $v !== null && $v !== '');
                if (empty($cellulesNonVides)) {
                    continue;
                }

                $nbLignes++;
                if ($nbLignes > self::MAX_LIGNES_PAR_FEUILLE) {
                    $texte .= "[... tronque a " . self::MAX_LIGNES_PAR_FEUILLE . " lignes ...]\n";
                    break;
                }

                // Tab-separated pour preserver la lecture par le LLM
                $texte .= implode("\t", array_map(fn ($v) => trim((string) $v), $ligne)) . "\n";
            }
        }

        return trim($texte);
    }

    public function supporte(string $typeMime): bool
    {
        return in_array($typeMime, [
            // Excel modernes
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
            'application/vnd.ms-excel', // .xls (et .xlsx mal detecte par certains navigateurs)
            // OpenDocument
            'application/vnd.oasis.opendocument.spreadsheet', // .ods
            // CSV — les navigateurs envoient parfois text/plain, on couvre les deux
            'text/csv',
            'application/csv',
        ], true);
    }
}
