<?php

/**
 * Service EcartRapportGenerator — Genere le rapport d'ecarts au format .docx.
 *
 * Utilise PhpOffice\PhpWord. Stocke le fichier sur le disque 'local'
 * dans le dossier rapports/analyses et met a jour rapport_word_path sur l'analyse.
 */

namespace App\Services\Analyse;

use App\Models\Analyse;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Language;

class EcartRapportGenerator
{
    private const COULEURS_GRAVITE = [
        'critique' => 'C0392B',
        'majeur' => 'E67E22',
        'mineur' => 'F1C40F',
        'observation' => '3498DB',
    ];

    public function generer(Analyse $analyse): string
    {
        $analyse->load(['mission.client', 'lanceur', 'ecarts.referentiel', 'ecarts.document']);

        $phpWord = new PhpWord();
        $phpWord->getSettings()->setThemeFontLang(new Language(Language::FR_FR));
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $this->enregistrerStyles($phpWord);

        $this->sectionCouverture($phpWord, $analyse);
        $this->sectionSynthese($phpWord, $analyse);
        $this->sectionTableauEcarts($phpWord, $analyse);
        $this->sectionDetailEcarts($phpWord, $analyse);
        $this->sectionPlanAction($phpWord, $analyse);

        $dossier = 'rapports/analyses';
        $nomFichier = sprintf('rapport-ecarts-%s-%s.docx',
            $analyse->reference,
            now()->format('Ymd-His')
        );
        $cheminRelatif = $dossier . '/' . $nomFichier;
        $cheminAbsolu = Storage::disk('local')->path($cheminRelatif);

        if (! is_dir(dirname($cheminAbsolu))) {
            mkdir(dirname($cheminAbsolu), 0775, true);
        }

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($cheminAbsolu);

        $analyse->update(['rapport_word_path' => $cheminRelatif]);

        return $cheminRelatif;
    }

    private function enregistrerStyles(PhpWord $phpWord): void
    {
        $phpWord->addTitleStyle(1, ['name' => 'Calibri', 'size' => 20, 'bold' => true, 'color' => '1A5490']);
        $phpWord->addTitleStyle(2, ['name' => 'Calibri', 'size' => 15, 'bold' => true, 'color' => '2C3E50']);
        $phpWord->addTitleStyle(3, ['name' => 'Calibri', 'size' => 13, 'bold' => true, 'color' => '34495E']);
        $phpWord->addFontStyle('critique', ['bold' => true, 'color' => self::COULEURS_GRAVITE['critique']]);
        $phpWord->addFontStyle('majeur', ['bold' => true, 'color' => self::COULEURS_GRAVITE['majeur']]);
        $phpWord->addFontStyle('mineur', ['bold' => true, 'color' => self::COULEURS_GRAVITE['mineur']]);
        $phpWord->addFontStyle('petit', ['size' => 9, 'color' => '7F8C8D', 'italic' => true]);
        $phpWord->addParagraphStyle('centre', ['alignment' => Jc::CENTER]);
    }

    private function sectionCouverture(PhpWord $phpWord, Analyse $analyse): void
    {
        $section = $phpWord->addSection();
        $section->addTextBreak(5);
        $section->addText('RAPPORT D\'ANALYSE DES ECARTS', ['size' => 28, 'bold' => true, 'color' => '1A5490'], 'centre');
        $section->addText('DE CONFORMITE', ['size' => 22, 'bold' => true, 'color' => '1A5490'], 'centre');
        $section->addTextBreak(2);

        $client = $analyse->mission?->client;
        $section->addText($client?->raison_sociale ?? 'Client', ['size' => 18, 'bold' => true], 'centre');
        if ($client?->sigle) {
            $section->addText($client->sigle, ['size' => 14, 'italic' => true], 'centre');
        }
        $section->addTextBreak(3);

        $section->addText('Reference : ' . $analyse->reference, ['size' => 12], 'centre');
        $section->addText('Mission : ' . ($analyse->mission?->reference ?? '') . ' - ' . ($analyse->mission?->titre ?? ''), ['size' => 12], 'centre');
        $section->addText('Date : ' . optional($analyse->terminee_a ?? $analyse->created_at)->format('d/m/Y'), ['size' => 12], 'centre');
        $section->addText('Etabli par : ' . trim(($analyse->lanceur->prenom ?? '') . ' ' . ($analyse->lanceur->nom ?? '')), ['size' => 12], 'centre');

        $section->addTextBreak(4);
        $section->addText('Document confidentiel — Usage interne exclusivement', 'petit', 'centre');
        $section->addPageBreak();
    }

    private function sectionSynthese(PhpWord $phpWord, Analyse $analyse): void
    {
        $section = $phpWord->addSection();
        $section->addTitle('1. Synthese executive', 1);

        $section->addTitle('1.1 Perimetre de l\'analyse', 2);
        $refs = $analyse->referentiels()->pluck('titre')->join(', ');
        $nbDocs = count($analyse->documents_ids ?? []);
        $section->addText("L'analyse a porte sur {$nbDocs} document(s) fourni(s) par le client, evalue(s) au regard des referentiels suivants : {$refs}.");

        $section->addTitle('1.2 Score de conformite', 2);
        $section->addText(sprintf('Score global : %s / 100', $analyse->score_conformite ?? '0'), ['bold' => true, 'size' => 14]);

        $section->addTitle('1.3 Repartition des ecarts', 2);
        $table = $section->addTable(['borderSize' => 6, 'borderColor' => 'CCCCCC', 'cellMargin' => 80]);
        $table->addRow();
        $table->addCell(3000, ['bgColor' => 'ECEFF1'])->addText('Gravite', ['bold' => true]);
        $table->addCell(3000, ['bgColor' => 'ECEFF1'])->addText('Nombre', ['bold' => true]);

        $lignes = [
            ['Critiques', $analyse->nb_ecarts_critiques, 'critique'],
            ['Majeurs', $analyse->nb_ecarts_majeurs, 'majeur'],
            ['Mineurs', $analyse->nb_ecarts_mineurs, 'mineur'],
        ];
        foreach ($lignes as [$label, $nb, $style]) {
            $table->addRow();
            $table->addCell(3000)->addText($label, $style);
            $table->addCell(3000)->addText((string) $nb);
        }
        $table->addRow();
        $table->addCell(3000, ['bgColor' => 'FAFAFA'])->addText('Exigences verifiees', ['bold' => true]);
        $table->addCell(3000, ['bgColor' => 'FAFAFA'])->addText((string) $analyse->nb_exigences_verifiees, ['bold' => true]);

        if ($analyse->commentaire_ia) {
            $section->addTextBreak(1);
            $section->addTitle('1.4 Appreciation generale', 2);
            $section->addText($analyse->commentaire_ia);
        }
    }

    private function sectionTableauEcarts(PhpWord $phpWord, Analyse $analyse): void
    {
        $section = $phpWord->addSection();
        $section->addTitle('2. Tableau synthetique des ecarts', 1);

        $ecarts = $analyse->ecarts()->orderByRaw("CASE gravite WHEN 'critique' THEN 1 WHEN 'majeur' THEN 2 WHEN 'mineur' THEN 3 ELSE 4 END")->get();

        if ($ecarts->isEmpty()) {
            $section->addText('Aucun ecart detecte. Felicitations !', ['bold' => true]);
            return;
        }

        $table = $section->addTable(['borderSize' => 6, 'borderColor' => 'BDC3C7', 'cellMargin' => 60]);
        $table->addRow(400);
        $headers = ['#', 'Gravite', 'Categorie', 'Article', 'Titre de l\'ecart'];
        $widths = [500, 1500, 1800, 1500, 5000];
        foreach ($headers as $i => $h) {
            $table->addCell($widths[$i], ['bgColor' => '1A5490'])->addText($h, ['bold' => true, 'color' => 'FFFFFF']);
        }

        foreach ($ecarts as $i => $ecart) {
            $table->addRow();
            $table->addCell($widths[0])->addText((string) ($i + 1));
            $table->addCell($widths[1])->addText(ucfirst($ecart->gravite), $ecart->gravite);
            $table->addCell($widths[2])->addText(ucfirst($ecart->categorie));
            $table->addCell($widths[3])->addText($ecart->article_reference ?? '-');
            $table->addCell($widths[4])->addText(mb_substr($ecart->titre, 0, 200));
        }
    }

    private function sectionDetailEcarts(PhpWord $phpWord, Analyse $analyse): void
    {
        $section = $phpWord->addSection();
        $section->addTitle('3. Detail des ecarts', 1);

        $ecarts = $analyse->ecarts()->with('referentiel', 'document')
            ->orderByRaw("CASE gravite WHEN 'critique' THEN 1 WHEN 'majeur' THEN 2 WHEN 'mineur' THEN 3 ELSE 4 END")
            ->get();

        foreach ($ecarts as $i => $ecart) {
            $section->addTitle(($i + 1) . '. ' . $ecart->titre, 2);

            $info = $section->addTable(['borderSize' => 4, 'borderColor' => 'E0E0E0', 'cellMargin' => 50]);
            $info->addRow();
            $info->addCell(2500, ['bgColor' => 'F5F5F5'])->addText('Gravite', ['bold' => true]);
            $info->addCell(7500)->addText(ucfirst($ecart->gravite), $ecart->gravite);
            $info->addRow();
            $info->addCell(2500, ['bgColor' => 'F5F5F5'])->addText('Categorie', ['bold' => true]);
            $info->addCell(7500)->addText(ucfirst($ecart->categorie));
            $info->addRow();
            $info->addCell(2500, ['bgColor' => 'F5F5F5'])->addText('Type d\'ecart', ['bold' => true]);
            $info->addCell(7500)->addText(str_replace('_', ' ', ucfirst($ecart->type_ecart)));
            $info->addRow();
            $info->addCell(2500, ['bgColor' => 'F5F5F5'])->addText('Referentiel', ['bold' => true]);
            $info->addCell(7500)->addText(($ecart->referentiel->code ?? '') . ' - ' . ($ecart->referentiel->titre ?? ''));
            if ($ecart->article_reference) {
                $info->addRow();
                $info->addCell(2500, ['bgColor' => 'F5F5F5'])->addText('Article', ['bold' => true]);
                $info->addCell(7500)->addText($ecart->article_reference);
            }
            if ($ecart->document) {
                $info->addRow();
                $info->addCell(2500, ['bgColor' => 'F5F5F5'])->addText('Document source', ['bold' => true]);
                $info->addCell(7500)->addText($ecart->document->titre);
            }

            $section->addTextBreak(1);
            $section->addTitle('Exigence du referentiel', 3);
            $section->addText(mb_substr($ecart->exigence_referentiel, 0, 1500), ['italic' => true], ['indentation' => ['left' => 300]]);

            $section->addTitle('Constat', 3);
            $section->addText($ecart->description_ecart);

            if ($ecart->extrait_document) {
                $section->addTitle('Extrait du document client', 3);
                $section->addText('« ' . mb_substr($ecart->extrait_document, 0, 800) . ' »', ['italic' => true, 'color' => '555555'], ['indentation' => ['left' => 300]]);
            }

            if ($ecart->recommandation) {
                $section->addTitle('Recommandation', 3);
                $section->addText($ecart->recommandation, ['bold' => true]);
            }

            $section->addTextBreak(2);
        }
    }

    private function sectionPlanAction(PhpWord $phpWord, Analyse $analyse): void
    {
        $section = $phpWord->addSection();
        $section->addTitle('4. Plan d\'action propose', 1);

        $ecarts = $analyse->ecarts()
            ->orderByRaw("CASE gravite WHEN 'critique' THEN 1 WHEN 'majeur' THEN 2 WHEN 'mineur' THEN 3 ELSE 4 END")
            ->get();

        if ($ecarts->isEmpty()) {
            $section->addText('Aucune action corrective requise.');
            return;
        }

        $table = $section->addTable(['borderSize' => 6, 'borderColor' => 'BDC3C7', 'cellMargin' => 60]);
        $table->addRow(400);
        foreach (['Priorite', 'Action recommandee', 'Echeance'] as $h) {
            $table->addCell(3000, ['bgColor' => '1A5490'])->addText($h, ['bold' => true, 'color' => 'FFFFFF']);
        }

        foreach ($ecarts as $ecart) {
            $priorite = match ($ecart->gravite) {
                'critique' => 'P1 - Immediate',
                'majeur' => 'P2 - Court terme',
                'mineur' => 'P3 - Moyen terme',
                default => 'P4',
            };
            $echeance = match ($ecart->gravite) {
                'critique' => '30 jours',
                'majeur' => '90 jours',
                'mineur' => '6 mois',
                default => 'Opportuniste',
            };

            $table->addRow();
            $table->addCell(3000)->addText($priorite, $ecart->gravite);
            $table->addCell(5000)->addText($ecart->recommandation ?? $ecart->titre);
            $table->addCell(2000)->addText($echeance);
        }
    }
}
