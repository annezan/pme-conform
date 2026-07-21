<?php

/**
 * Service RapportPptxGenerator — Genere le rapport d'ecarts au format .pptx.
 *
 * Reproduit exactement la structure du modele "Rapport de Risques Priorises" :
 *   - 1 slide par domaine/categorie (gouvernance, juridique, technique, ...)
 *   - Titre "Domaine : [nom]"
 *   - Tableau 3 colonnes : CONSTAT TERRAIN | VIOLATION IDENTIFIEE | VERDICT & RISQUE
 *   - Emojis gravite (rouge critique, orange majeur, jaune mineur/modere)
 */

namespace App\Services\Analyse;

use App\Models\Analyse;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Shape\RichText\Paragraph;
use PhpOffice\PhpPresentation\Shape\Table;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Border;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Style\Fill;

class RapportPptxGenerator
{
    private const COULEURS = [
        'critique' => ['hex' => 'C0392B', 'emoji' => '🔴', 'label' => 'CRITIQUE'],
        'majeur' => ['hex' => 'E67E22', 'emoji' => '🟠', 'label' => 'MAJEUR'],
        'mineur' => ['hex' => 'F1C40F', 'emoji' => '🟡', 'label' => 'MODERE'],
        'observation' => ['hex' => '3498DB', 'emoji' => '🔵', 'label' => 'OBSERVATION'],
    ];

    private const LABEL_CATEGORIES = [
        'gouvernance' => 'Gouvernance',
        'juridique' => 'Juridique',
        'technique' => 'Informatique, Securite & Enjeux Transverses',
        'organisationnelle' => 'Administration & RH',
        'documentaire' => 'Documentation & Archivage',
        'autre' => 'Autres domaines',
    ];

    private const LARGEUR_SLIDE_EMU = 12192000; // 13.33 pouces
    private const HAUTEUR_SLIDE_EMU = 6858000;  // 7.5 pouces

    public function generer(Analyse $analyse): string
    {
        $analyse->load(['mission.client', 'lanceur', 'ecarts.referentiel', 'ecarts.document']);

        $ppt = new PhpPresentation();
        $ppt->getLayout()->setDocumentLayout([
            'cx' => self::LARGEUR_SLIDE_EMU,
            'cy' => self::HAUTEUR_SLIDE_EMU,
        ], true);

        // Ajuster proprietes
        $ppt->getDocumentProperties()
            ->setCreator(trim(($analyse->lanceur->prenom ?? '') . ' ' . ($analyse->lanceur->nom ?? '')))
            ->setTitle('Rapport de Risques Prioriss - ' . $analyse->reference)
            ->setSubject($analyse->mission?->client?->raison_sociale ?? '')
            ->setDescription('Analyse de conformite ARTCI');

        // PhpPresentation cree automatiquement 1 slide vide
        $slideCouverture = $ppt->getActiveSlide();
        $this->construireCouverture($slideCouverture, $analyse);

        // Slide 2 : synthese
        $slideSynthese = $ppt->createSlide();
        $this->construireSynthese($slideSynthese, $analyse);

        // 1 slide par domaine
        $ecartsParCategorie = $analyse->ecarts()
            ->with(['referentiel', 'document'])
            ->orderByRaw("CASE gravite WHEN 'critique' THEN 1 WHEN 'majeur' THEN 2 WHEN 'mineur' THEN 3 ELSE 4 END")
            ->get()
            ->groupBy('categorie');

        $numeroSlide = 3;
        foreach ($ecartsParCategorie as $categorie => $ecarts) {
            // Limite 3 ecarts par slide pour eviter les debordements
            $chunks = $ecarts->chunk(3);
            foreach ($chunks as $groupe) {
                $slide = $ppt->createSlide();
                $this->construireSlideDomaine($slide, $categorie, $groupe, $numeroSlide++, $analyse);
            }
        }

        // Slide finale : plan d'action
        $slideAction = $ppt->createSlide();
        $this->construirePlanAction($slideAction, $analyse);

        // Sauvegarde
        $dossier = 'rapports/analyses';
        $nomFichier = sprintf('rapport-risques-%s-%s.pptx',
            $analyse->reference,
            now()->format('Ymd-His')
        );
        $cheminRelatif = $dossier . '/' . $nomFichier;
        $cheminAbsolu = Storage::disk('local')->path($cheminRelatif);

        if (! is_dir(dirname($cheminAbsolu))) {
            mkdir(dirname($cheminAbsolu), 0775, true);
        }

        $writer = IOFactory::createWriter($ppt, 'PowerPoint2007');
        $writer->save($cheminAbsolu);

        $analyse->update(['rapport_word_path' => $cheminRelatif]);

        return $cheminRelatif;
    }

    private function construireCouverture($slide, Analyse $analyse): void
    {
        $client = $analyse->mission?->client;

        // Bande de couleur en haut
        $shape = $slide->createRichTextShape()
            ->setWidth((int)(self::LARGEUR_SLIDE_EMU * 0.9 / 9525))
            ->setHeight(100)
            ->setOffsetX(60)
            ->setOffsetY(150);
        $shape->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('FF1A5490'));
        $p = $shape->createTextRun('RAPPORT DE RISQUES PRIORISES');
        $p->getFont()->setBold(true)->setSize(32)->setColor(new Color('FFFFFFFF'));
        $shape->getActiveParagraph()->setAlignment($this->alignCentre());

        // Sous-titre
        $st = $slide->createRichTextShape()->setWidth(1200)->setHeight(60)->setOffsetX(60)->setOffsetY(260);
        $st->createTextRun('Analyse de conformite ARTCI')
            ->getFont()->setItalic(true)->setSize(18)->setColor(new Color('FF2C3E50'));
        $st->getActiveParagraph()->setAlignment($this->alignCentre());

        // Bloc Client
        $b = $slide->createRichTextShape()->setWidth(1200)->setHeight(300)->setOffsetX(60)->setOffsetY(380);
        $b->createParagraph();
        $b->createTextRun($client?->raison_sociale ?? 'Client')
            ->getFont()->setBold(true)->setSize(28)->setColor(new Color('FF1A5490'));
        $b->getActiveParagraph()->setAlignment($this->alignCentre());

        if ($client?->sigle) {
            $b->createParagraph()->createTextRun($client->sigle)
                ->getFont()->setItalic(true)->setSize(16)->setColor(new Color('FF7F8C8D'));
            $b->getActiveParagraph()->setAlignment($this->alignCentre());
        }

        // Footer meta
        $f = $slide->createRichTextShape()->setWidth(1200)->setHeight(120)->setOffsetX(60)->setOffsetY(650);
        $f->createTextRun('Reference : ' . $analyse->reference)
            ->getFont()->setSize(12)->setColor(new Color('FF2C3E50'));
        $f->getActiveParagraph()->setAlignment($this->alignCentre());

        $f->createParagraph()->createTextRun('Mission : ' . ($analyse->mission?->reference ?? '') . ' - ' . ($analyse->mission?->titre ?? ''))
            ->getFont()->setSize(12)->setColor(new Color('FF2C3E50'));
        $f->getActiveParagraph()->setAlignment($this->alignCentre());

        $f->createParagraph()->createTextRun('Date : ' . optional($analyse->terminee_a ?? $analyse->created_at)->format('d/m/Y'))
            ->getFont()->setSize(12)->setColor(new Color('FF2C3E50'));
        $f->getActiveParagraph()->setAlignment($this->alignCentre());

        $f->createParagraph()->createTextRun('Etabli par : ' . trim(($analyse->lanceur->prenom ?? '') . ' ' . ($analyse->lanceur->nom ?? '')))
            ->getFont()->setSize(12)->setColor(new Color('FF2C3E50'));
        $f->getActiveParagraph()->setAlignment($this->alignCentre());
    }

    private function construireSynthese($slide, Analyse $analyse): void
    {
        // Titre
        $titre = $slide->createRichTextShape()->setWidth(1200)->setHeight(80)->setOffsetX(60)->setOffsetY(40);
        $titre->createTextRun('Synthese Executive')
            ->getFont()->setBold(true)->setSize(28)->setColor(new Color('FF1A5490'));

        // Stats
        $stats = $slide->createRichTextShape()->setWidth(1200)->setHeight(180)->setOffsetX(60)->setOffsetY(150);
        $stats->createTextRun(sprintf(
            'Score de conformite global : %s / 100',
            $analyse->score_conformite ?? '0'
        ))->getFont()->setBold(true)->setSize(20)->setColor(new Color('FF2C3E50'));

        $stats->createParagraph();
        $stats->createParagraph()->createTextRun(sprintf(
            '%d exigences verifiees  •  %d ecarts critiques  •  %d ecarts majeurs  •  %d ecarts mineurs',
            $analyse->nb_exigences_verifiees,
            $analyse->nb_ecarts_critiques,
            $analyse->nb_ecarts_majeurs,
            $analyse->nb_ecarts_mineurs
        ))->getFont()->setSize(14)->setColor(new Color('FF5D6D7E'));

        // Commentaire IA
        if ($analyse->commentaire_ia) {
            $c = $slide->createRichTextShape()->setWidth(1200)->setHeight(400)->setOffsetX(60)->setOffsetY(380);
            $c->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('FFF5F8FA'));
            $c->createTextRun($analyse->commentaire_ia)
                ->getFont()->setSize(13)->setColor(new Color('FF2C3E50'));
        }
    }

    private function construireSlideDomaine($slide, string $categorie, $ecarts, int $numeroSlide, Analyse $analyse): void
    {
        $labelDomaine = self::LABEL_CATEGORIES[$categorie] ?? ucfirst($categorie);

        // Numero de slide en haut a gauche
        $num = $slide->createRichTextShape()->setWidth(100)->setHeight(50)->setOffsetX(40)->setOffsetY(20);
        $num->createTextRun((string) $numeroSlide)
            ->getFont()->setBold(true)->setSize(20)->setColor(new Color('FF1A5490'));

        // Titre du domaine
        $titre = $slide->createRichTextShape()->setWidth(1100)->setHeight(60)->setOffsetX(140)->setOffsetY(25);
        $run1 = $titre->createTextRun('Domaine : ');
        $run1->getFont()->setBold(true)->setSize(22)->setColor(new Color('FF2C3E50'));
        $run2 = $titre->createTextRun($labelDomaine);
        $run2->getFont()->setBold(true)->setSize(22)->setColor(new Color('FF1A5490'));

        // Tableau 3 colonnes — max 3 ecarts par slide, hauteur fixe pour eviter debordement
        $table = $slide->createTableShape(3)
            ->setWidth(1220)
            ->setOffsetX(40)
            ->setOffsetY(100);

        // Largeurs reequilibrees : CONSTAT plus etroit, VIOLATION plus large
        $largeurs = [340, 500, 380]; // somme = 1220

        // Entete
        $rowHeader = $table->createRow();
        $rowHeader->setHeight(35);
        $entetes = ['CONSTAT TERRAIN (PREUVE)', 'VIOLATION IDENTIFIEE', 'VERDICT & RISQUE'];
        foreach ($entetes as $i => $txt) {
            $cell = $rowHeader->getCell($i);
            $cell->setWidth($largeurs[$i]);
            $cell->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('FF1A5490'));
            $this->appliquerBordures($cell);
            $p = $cell->createTextRun($txt);
            $p->getFont()->setBold(true)->setSize(10)->setColor(new Color('FFFFFFFF'));
            $cell->getActiveParagraph()->setAlignment($this->alignCentre());
        }

        // Lignes : hauteur calculee selon nb ecarts, stricte pour eviter depassement
        $hauteurDispo = 570; // hauteur totale disponible pour les lignes
        $nbEcarts = count($ecarts);
        $hauteurLigne = (int) min(180, max(130, $hauteurDispo / max(1, $nbEcarts)));

        $numeroConstat = 1;
        foreach ($ecarts as $ecart) {
            $row = $table->createRow();
            $row->setHeight($hauteurLigne);

            // Cellule 1 : CONSTAT
            $c1 = $row->getCell(0);
            $c1->setWidth($largeurs[0]);
            $this->appliquerBordures($c1);
            $this->construireCelluleConstat($c1, $ecart, $numeroConstat++);

            // Cellule 2 : VIOLATION
            $c2 = $row->getCell(1);
            $c2->setWidth($largeurs[1]);
            $this->appliquerBordures($c2);
            $this->construireCelluleViolation($c2, $ecart);

            // Cellule 3 : VERDICT
            $c3 = $row->getCell(2);
            $c3->setWidth($largeurs[2]);
            $this->appliquerBordures($c3);
            $this->construireCelluleVerdict($c3, $ecart);
        }
    }

    private function construireCelluleConstat($cell, $ecart, int $numero): void
    {
        // Titre abrege : "Constat N" uniquement (le detail du type va dans la suite)
        $typeCourt = match ($ecart->type_ecart) {
            'absence_totale' => 'Absence preuve',
            'preuve_insuffisante' => 'Preuve insuffisante',
            'non_conformite' => 'Non-conformite',
            'obsolete' => 'Obsolete',
            default => 'Ecart',
        };
        $titreRun = $cell->createTextRun("Constat {$numero} — {$typeCourt}");
        $titreRun->getFont()->setBold(true)->setSize(9)->setColor(new Color('FF2C3E50'));

        if ($ecart->extrait_document) {
            $cell->createBreak();
            $extrait = $this->tronquerIntelligent($ecart->extrait_document, 180);
            $run = $cell->createTextRun('« ' . $extrait . ' »');
            $run->getFont()->setItalic(true)->setSize(8)->setColor(new Color('FF5D6D7E'));
        }

        if ($ecart->document) {
            $cell->createBreak();
            $run = $cell->createTextRun('Source : ' . mb_substr($ecart->document->titre, 0, 40));
            $run->getFont()->setSize(7)->setColor(new Color('FF95A5A6'))->setItalic(true);
        }
    }

    private function construireCelluleViolation($cell, $ecart): void
    {
        if ($ecart->referentiel) {
            $ref = $cell->createTextRun($ecart->referentiel->code . ($ecart->article_reference ? ' — ' . $ecart->article_reference : ''));
            $ref->getFont()->setBold(true)->setSize(9)->setColor(new Color('FF1A5490'));
            $cell->createBreak();
        }

        $exigence = $this->tronquerIntelligent($ecart->exigence_referentiel, 220);
        $run = $cell->createTextRun($exigence);
        $run->getFont()->setSize(8)->setColor(new Color('FF2C3E50'));
    }

    private function construireCelluleVerdict($cell, $ecart): void
    {
        $cfg = self::COULEURS[$ecart->gravite] ?? self::COULEURS['majeur'];

        // Emoji + label gravite
        $verdictRun = $cell->createTextRun($cfg['emoji'] . ' ' . $cfg['label']);
        $verdictRun->getFont()->setBold(true)->setSize(11)->setColor(new Color('FF' . $cfg['hex']));

        if ($ecart->description_ecart) {
            $cell->createBreak();
            $desc = $this->tronquerIntelligent($ecart->description_ecart, 150);
            $run = $cell->createTextRun($desc);
            $run->getFont()->setSize(8)->setColor(new Color('FF2C3E50'));
        }

        if ($ecart->recommandation) {
            $cell->createBreak();
            $reco = $this->tronquerIntelligent($ecart->recommandation, 110);
            $run = $cell->createTextRun('Reco : ' . $reco);
            $run->getFont()->setSize(7)->setColor(new Color('FF27AE60'))->setItalic(true);
        }
    }

    /**
     * Tronque un texte a la limite en cherchant le dernier espace avant,
     * pour eviter de couper au milieu d'un mot. Ajoute "..." si tronque.
     */
    private function tronquerIntelligent(string $texte, int $limite): string
    {
        $texte = trim(preg_replace('/\s+/u', ' ', $texte));
        if (mb_strlen($texte) <= $limite) {
            return $texte;
        }
        $tronque = mb_substr($texte, 0, $limite);
        $pos = mb_strrpos($tronque, ' ');
        if ($pos !== false && $pos > $limite * 0.7) {
            $tronque = mb_substr($tronque, 0, $pos);
        }
        return $tronque . '...';
    }

    private function construirePlanAction($slide, Analyse $analyse): void
    {
        $titre = $slide->createRichTextShape()->setWidth(1200)->setHeight(80)->setOffsetX(60)->setOffsetY(40);
        $titre->createTextRun('Plan d\'Action Propose')
            ->getFont()->setBold(true)->setSize(28)->setColor(new Color('FF1A5490'));

        $ecarts = $analyse->ecarts()
            ->orderByRaw("CASE gravite WHEN 'critique' THEN 1 WHEN 'majeur' THEN 2 WHEN 'mineur' THEN 3 ELSE 4 END")
            ->limit(10)
            ->get();

        if ($ecarts->isEmpty()) {
            $t = $slide->createRichTextShape()->setWidth(1200)->setHeight(100)->setOffsetX(60)->setOffsetY(150);
            $t->createTextRun('Aucune action corrective requise.')
                ->getFont()->setSize(14)->setColor(new Color('FF27AE60'));
            return;
        }

        $table = $slide->createTableShape(3)->setWidth(1220)->setOffsetX(40)->setOffsetY(140);
        $largeurs = [200, 820, 200];

        $row = $table->createRow();
        $row->setHeight(40);
        foreach (['Priorite', 'Action recommandee', 'Echeance'] as $i => $h) {
            $cell = $row->getCell($i);
            $cell->setWidth($largeurs[$i]);
            $cell->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('FF1A5490'));
            $this->appliquerBordures($cell);
            $p = $cell->createTextRun($h);
            $p->getFont()->setBold(true)->setSize(11)->setColor(new Color('FFFFFFFF'));
            $cell->getActiveParagraph()->setAlignment($this->alignCentre());
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
            $cfg = self::COULEURS[$ecart->gravite] ?? self::COULEURS['majeur'];

            $r = $table->createRow();
            $r->setHeight(45);

            $c1 = $r->getCell(0);
            $c1->setWidth($largeurs[0]);
            $this->appliquerBordures($c1);
            $c1->createTextRun($priorite)
                ->getFont()->setBold(true)->setSize(10)->setColor(new Color('FF' . $cfg['hex']));

            $c2 = $r->getCell(1);
            $c2->setWidth($largeurs[1]);
            $this->appliquerBordures($c2);
            $c2->createTextRun(mb_substr($ecart->recommandation ?? $ecart->titre, 0, 200))
                ->getFont()->setSize(10)->setColor(new Color('FF2C3E50'));

            $c3 = $r->getCell(2);
            $c3->setWidth($largeurs[2]);
            $this->appliquerBordures($c3);
            $c3->createTextRun($echeance)
                ->getFont()->setSize(10)->setColor(new Color('FF2C3E50'));
        }
    }

    private function alignCentre(): Alignment
    {
        $a = new Alignment();
        $a->setHorizontal(Alignment::HORIZONTAL_CENTER);
        return $a;
    }

    private function appliquerBordures($cell): void
    {
        try {
            $borders = $cell->getBorders();
            foreach (['Top', 'Bottom', 'Left', 'Right'] as $cote) {
                $getter = 'get' . $cote;
                if (method_exists($borders, $getter)) {
                    $borders->{$getter}()
                        ->setLineStyle(Border::LINE_SINGLE)
                        ->setLineWidth(1)
                        ->setColor(new Color('FFBDC3C7'));
                }
            }
        } catch (\Throwable $e) {
            // Bordures facultatives, on ignore
        }

        // Autofit vertical : si trop de texte, PowerPoint reduit la police automatiquement
        try {
            if (method_exists($cell, 'setAutoFit')) {
                $cell->setAutoFit(\PhpOffice\PhpPresentation\Shape\RichText::AUTOFIT_NORMAL);
            }
        } catch (\Throwable $e) {
            // best-effort
        }

        // Marges internes reduites pour plus de contenu visible
        try {
            if (method_exists($cell, 'setInsetLeft')) {
                $cell->setInsetLeft(5);
                $cell->setInsetRight(5);
                $cell->setInsetTop(4);
                $cell->setInsetBottom(4);
            }
        } catch (\Throwable $e) {
            // best-effort
        }
    }
}
