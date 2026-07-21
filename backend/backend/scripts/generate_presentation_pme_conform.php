<?php

/**
 * Genere "PRESENTATION_PME-CONFORM.pptx" — support de presentation interne
 * AS Consulting. Design epure : header colore en bandeau, fond blanc, texte
 * lisible, cartes contrastees.
 *
 * NB : PhpPresentation utilise des couleurs au format alpha-RGB en 8 caracteres
 * (FF + RRGGBB). Les couleurs en 6 caracteres ne sont pas correctement rendues.
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\Slide;
use PhpOffice\PhpPresentation\Slide\Background\Color as SlideBgColor;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Style\Fill;
use PhpOffice\PhpPresentation\Style\Border;

// ============================================================
// PALETTE (alpha + RGB) — toujours 8 chars
// ============================================================
const C_BLEU       = 'FF1E3A8A'; // primaire
const C_BLEU_VIF   = 'FF2563EB';
const C_BLEU_PALE  = 'FFEFF6FF';
const C_NOIR       = 'FF111827';
const C_GRIS       = 'FF6B7280';
const C_GRIS_LEGER = 'FFF3F4F6';
const C_BLANC      = 'FFFFFFFF';
const C_VERT       = 'FF059669';
const C_VERT_PALE  = 'FFD1FAE5';
const C_ORANGE     = 'FFD97706';
const C_ORANGE_PALE = 'FFFEF3C7';
const C_ROUGE      = 'FFB91C1C';
const C_ROUGE_PALE = 'FFFEE2E2';
const C_VIOLET     = 'FF6D28D9';
const C_VIOLET_PALE = 'FFEDE9FE';

// ============================================================
// Init presentation 16:9
// ============================================================
const LARGEUR_EMU = 12192000;
const HAUTEUR_EMU = 6858000;
const SLIDE_W = 1280; // unites pixels equivalents (12192000 / 9525)
const SLIDE_H = 720;

$ppt = new PhpPresentation();
$ppt->getLayout()->setDocumentLayout([
    'cx' => LARGEUR_EMU,
    'cy' => HAUTEUR_EMU,
], true);

$ppt->getDocumentProperties()
    ->setCreator('AS Consulting')
    ->setTitle('PME-CONFORM — Presentation interne')
    ->setSubject('Plateforme de conformite reglementaire')
    ->setDescription('Support de presentation aux equipes AS Consulting');

// ============================================================
// Helpers
// ============================================================

/**
 * Ajoute une forme rectangulaire pleine (sans texte). Utilisee pour les
 * bandeaux, cartes et fonds de section.
 */
function rect(Slide $s, int $x, int $y, int $w, int $h, string $couleur, ?string $bordure = null, int $bordureW = 1): void
{
    $shape = $s->createRichTextShape();
    $shape->setOffsetX($x)->setOffsetY($y)->setWidth($w)->setHeight($h);
    $shape->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color($couleur));
    if ($bordure !== null) {
        $shape->getBorder()->setLineStyle(Border::LINE_SINGLE)->setLineWidth($bordureW)->setColor(new Color($bordure));
    } else {
        $shape->getBorder()->setLineStyle(Border::LINE_NONE);
    }
}

/**
 * Texte transparent (sans fond). Couleur appliquee a chaque run et chaque
 * paragraphe pour eviter les heritages perdus.
 */
function txt(
    Slide $s,
    string $texte,
    int $x,
    int $y,
    int $w,
    int $h,
    int $taille = 14,
    string $couleur = C_NOIR,
    bool $gras = false,
    string $align = Alignment::HORIZONTAL_LEFT,
    bool $italique = false,
    string $vertAlign = Alignment::VERTICAL_TOP
): void {
    $shape = $s->createRichTextShape();
    $shape->setOffsetX($x)->setOffsetY($y)->setWidth($w)->setHeight($h);
    $shape->getFill()->setFillType(Fill::FILL_NONE);
    $shape->getBorder()->setLineStyle(Border::LINE_NONE);

    $lignes = explode("\n", $texte);
    foreach ($lignes as $i => $ligne) {
        if ($i === 0) {
            $p = $shape->getActiveParagraph();
        } else {
            $p = $shape->createParagraph();
        }
        $p->getAlignment()->setHorizontal($align)->setVertical($vertAlign);
        $run = $p->createTextRun($ligne);
        $run->getFont()->setSize($taille);
        $run->getFont()->setBold($gras);
        $run->getFont()->setItalic($italique);
        $run->getFont()->setColor(new Color($couleur));
    }
}

/**
 * Liste a puces lisible : marge gauche, puce coloree, espace constant.
 */
function puces(Slide $s, array $items, int $x, int $y, int $w, int $h, int $taille = 14, string $couleurPuce = C_BLEU_VIF, string $couleurTexte = C_NOIR): void
{
    $shape = $s->createRichTextShape();
    $shape->setOffsetX($x)->setOffsetY($y)->setWidth($w)->setHeight($h);
    $shape->getFill()->setFillType(Fill::FILL_NONE);
    $shape->getBorder()->setLineStyle(Border::LINE_NONE);

    foreach ($items as $i => $texte) {
        $p = $i === 0 ? $shape->getActiveParagraph() : $shape->createParagraph();
        $p->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $puce = $p->createTextRun('●  ');
        $puce->getFont()->setSize($taille + 4)->setBold(true)->setColor(new Color($couleurPuce));

        $run = $p->createTextRun($texte);
        $run->getFont()->setSize($taille)->setColor(new Color($couleurTexte));
    }
}

/**
 * Pose un bandeau colore en haut + un titre en gras + sous-titre optionnel.
 */
function entete(Slide $s, string $titre, ?string $sousTitre, string $couleurBande = C_BLEU): void
{
    rect($s, 0, 0, SLIDE_W, 14, $couleurBande);            // bande tres fine en haut
    txt($s, $titre, 50, 35, SLIDE_W - 100, 60, 34, C_NOIR, true);
    if ($sousTitre) {
        txt($s, $sousTitre, 50, 88, SLIDE_W - 100, 35, 16, C_GRIS, false, Alignment::HORIZONTAL_LEFT, true);
    }
    rect($s, 50, 130, 80, 4, $couleurBande); // souligne accent
}

/**
 * Pied de page constant.
 */
function pied(Slide $s, int $num, int $total): void
{
    rect($s, 0, SLIDE_H - 30, SLIDE_W, 30, C_GRIS_LEGER);
    txt($s, 'PME-CONFORM   —   AS Consulting', 30, SLIDE_H - 26, 600, 22, 10, C_GRIS, false);
    txt($s, "{$num} / {$total}", SLIDE_W - 100, SLIDE_H - 26, 70, 22, 10, C_GRIS, true, Alignment::HORIZONTAL_RIGHT);
}

/**
 * Carte rectangulaire pour grouper du contenu (titre + items).
 */
function carteSection(Slide $s, int $x, int $y, int $w, int $h, string $titre, array $items, string $couleur, string $couleurFond = C_BLANC): void
{
    rect($s, $x, $y, $w, $h, $couleurFond, $couleur, 2);
    rect($s, $x, $y, $w, 38, $couleur); // bandeau titre
    txt($s, $titre, $x + 18, $y + 8, $w - 36, 28, 16, C_BLANC, true);
    puces($s, $items, $x + 18, $y + 50, $w - 36, $h - 64, 12, $couleur, C_NOIR);
}

/**
 * Cercle / pastille avec un numero d'etape.
 */
function pastille(Slide $s, string $num, int $x, int $y, int $taille, string $couleur): void
{
    rect($s, $x, $y, $taille, $taille, $couleur);
    txt($s, $num, $x, $y + (int)($taille / 4) - 2, $taille, $taille, 32, C_BLANC, true, Alignment::HORIZONTAL_CENTER);
}

/**
 * Carte KPI : grand chiffre + libelle.
 */
function kpi(Slide $s, string $valeur, string $libelle, int $x, int $y, int $w, int $h, string $couleur): void
{
    rect($s, $x, $y, $w, $h, C_BLANC, $couleur, 2);
    rect($s, $x, $y, $w, 8, $couleur); // accent haut
    txt($s, $valeur, $x, $y + 25, $w, 60, 36, $couleur, true, Alignment::HORIZONTAL_CENTER);
    txt($s, $libelle, $x + 10, $y + 90, $w - 20, $h - 100, 12, C_GRIS, false, Alignment::HORIZONTAL_CENTER);
}

/**
 * Badge plein (etiquette).
 */
function badge(Slide $s, string $texte, int $x, int $y, int $w, int $h, string $couleur): void
{
    rect($s, $x, $y, $w, $h, $couleur);
    txt($s, $texte, $x, $y + (int)($h / 4) - 2, $w, $h, 11, C_BLANC, true, Alignment::HORIZONTAL_CENTER);
}

$total = 16;

// ============================================================
// SLIDE 1 — COUVERTURE
// ============================================================
$s = $ppt->getActiveSlide();
$bg = new SlideBgColor();
$bg->setColor(new Color(C_BLEU));
$s->setBackground($bg);
// Securite : on pose aussi un grand rectangle bleu en premier (au cas ou
// le rendu du background ne s'applique pas correctement chez Word)
rect($s, 0, 0, SLIDE_W, SLIDE_H, C_BLEU);

// Bandeau orange en haut
rect($s, 0, 0, SLIDE_W, 18, C_ORANGE);
// Bandeau orange en bas (separateur)
rect($s, 0, SLIDE_H - 18, SLIDE_W, 18, C_ORANGE);

// Titre principal
txt($s, 'PME-CONFORM', 0, 220, SLIDE_W, 90, 64, C_BLANC, true, Alignment::HORIZONTAL_CENTER);
// Trait blanc decoratif
rect($s, (int)((SLIDE_W - 160) / 2), 320, 160, 4, C_BLANC);
// Sous-titre
txt($s, 'Plateforme de conformite reglementaire', 0, 345, SLIDE_W, 40, 22, 'FFBFDBFE', false, Alignment::HORIZONTAL_CENTER);
txt($s, 'AS Consulting', 0, 380, SLIDE_W, 30, 18, 'FFBFDBFE', false, Alignment::HORIZONTAL_CENTER, true);

// Bloc d'accroche en bas
txt($s, 'Presentation interne aux equipes', 0, 500, SLIDE_W, 40, 18, C_BLANC, true, Alignment::HORIZONTAL_CENTER);
txt($s, 'Recueil de vos idees, remarques et ameliorations', 0, 540, SLIDE_W, 30, 14, 'FFBFDBFE', false, Alignment::HORIZONTAL_CENTER, true);

// Date
txt($s, 'Reunion d\'alignement  —  ' . date('d/m/Y'), 0, 660, SLIDE_W, 30, 12, 'FFBFDBFE', false, Alignment::HORIZONTAL_CENTER);

// ============================================================
// SLIDE 2 — AGENDA
// ============================================================
$s = $ppt->createSlide();
entete($s, 'Agenda', 'Ce que nous allons couvrir aujourd\'hui', C_BLEU);

puces($s, [
    'Le probleme adresse — pourquoi PME-CONFORM',
    'Vision produit et positionnement',
    'Architecture fonctionnelle (Methode 1 et Methode 2)',
    'Workflow de bout en bout : de la mission a l\'analyse d\'ecarts',
    'Demonstration des ecrans cles',
    'Roadmap et prochaines etapes',
    'Atelier ouvert : vos idees, remarques, ameliorations',
], 90, 180, SLIDE_W - 180, SLIDE_H - 240, 18);

pied($s, 2, $total);

// ============================================================
// SLIDE 3 — LE PROBLEME
// ============================================================
$s = $ppt->createSlide();
entete($s, 'Le probleme adresse', 'Audits de conformite : un processus encore trop manuel', C_ROUGE);

// Carte rouge
rect($s, 50, 170, SLIDE_W - 100, 320, C_ROUGE_PALE, C_ROUGE, 2);
puces($s, [
    'Collecte documentaire chronophage : echanges email, relances, fichiers epars.',
    'Cartographie organisationnelle redessinee a chaque mission.',
    'Questionnaires d\'audit reconstruits manuellement, peu reutilisables.',
    'Croisement avec les referentiels (RGPD, ARTCI, RGSSI, CIMA) majoritairement humain.',
    'Risque d\'oubli, manque de tracabilite, rapport final non standardise.',
], 80, 195, SLIDE_W - 160, 290, 16, C_ROUGE);

txt($s, 'Consequence : du temps perdu pour le consultant, peu de visibilite pour le client, qualite variable selon l\'auditeur.',
    50, 530, SLIDE_W - 100, 60, 15, C_NOIR, true, Alignment::HORIZONTAL_LEFT, true);

pied($s, 3, $total);

// ============================================================
// SLIDE 4 — VISION PRODUIT
// ============================================================
$s = $ppt->createSlide();
entete($s, 'Vision produit', 'Automatiser la chaine de l\'audit, du brief a la remediation', C_VERT);

$colW = 390;
carteSection($s, 50, 165, $colW, 380, 'Pour le client', [
    'Espace dedie pour deposer ses preuves.',
    'Formulaires guides par pole et par secteur.',
    'Suivi en temps reel de l\'audit.',
    'Rapport telechargeable a la fin.',
], C_BLEU_VIF);

carteSection($s, 50 + $colW + 15, 165, $colW, 380, 'Pour le consultant ASC', [
    'Mission centralisee : matrice, organigramme, formulaires, documents.',
    'Generation IA des questionnaires.',
    'Analyse RAG : ecarts horodates et notes.',
    'Rapport PowerPoint genere automatiquement.',
], C_VERT);

carteSection($s, 50 + ($colW + 15) * 2, 165, $colW, 380, 'Pour la direction', [
    'Portefeuille consolide des missions.',
    'Indicateurs de conformite par client.',
    'Tracabilite complete (audit log).',
    'Reduction du time-to-rapport.',
], C_VIOLET);

pied($s, 4, $total);

// ============================================================
// SLIDE 5 — ARCHITECTURE FONCTIONNELLE
// ============================================================
$s = $ppt->createSlide();
entete($s, 'Architecture fonctionnelle', 'Une mission = une methode de travail', C_BLEU_VIF);

// Methode 1
rect($s, 60, 175, 580, 410, C_BLEU_PALE, C_BLEU_VIF, 2);
rect($s, 60, 175, 580, 50, C_BLEU_VIF);
txt($s, 'Methode 1   —   Classique', 78, 188, 540, 28, 20, C_BLANC, true);
txt($s, 'AS Consulting concoit le formulaire ; le client le remplit, ou l\'agent le saisit en interview (Google Meet, Zoom...).',
    78, 240, 540, 60, 13, C_NOIR, false);
puces($s, [
    'Creation du formulaire vierge a la mission.',
    'Edition des questions par le consultant.',
    'Saisie des reponses (client ou agent en interview).',
    'Croisement direct avec les referentiels.',
], 78, 320, 540, 250, 14, C_BLEU_VIF);

// Methode 2
rect($s, 660, 175, 580, 410, C_VIOLET_PALE, C_VIOLET, 2);
rect($s, 660, 175, 580, 50, C_VIOLET);
txt($s, 'Methode 2   —   IA dynamique', 678, 188, 540, 28, 20, C_BLANC, true);
txt($s, 'Matrice semaine 0 -> organigramme deduit -> questionnaires generes par l\'IA pour chaque pole / service.',
    678, 240, 540, 60, 13, C_NOIR, false);
puces($s, [
    'Matrice 5 poles pre-remplie pour le client.',
    'Derivation automatique de l\'organigramme.',
    'Generation IA des questionnaires d\'audit.',
    'Themes auto : biometrie, video, sous-traitance...',
], 678, 320, 540, 250, 14, C_VIOLET);

pied($s, 5, $total);

// ============================================================
// SLIDE 6 — WORKFLOW VUE D'ENSEMBLE
// ============================================================
$s = $ppt->createSlide();
entete($s, 'Workflow de bout en bout', 'De la creation de la mission au rapport d\'analyse', C_ORANGE);

$etapes = [
    ['1', 'Mission', 'Creation par ASC', C_BLEU_VIF],
    ['2', 'Cartographie', 'Matrice 5 poles (client)', C_VIOLET],
    ['3', 'Organigramme', 'Deduction + figeage', C_ORANGE],
    ['4', 'Formulaires', 'Reponses client/agent', C_VERT],
    ['5', 'Analyse', 'RAG + rapport PPTX', C_ROUGE],
];

$x = 50;
$gap = 18;
$cardW = (int)((SLIDE_W - 100 - 4 * $gap) / 5); // 5 cartes
$y = 200;
foreach ($etapes as $e) {
    rect($s, $x, $y, $cardW, 280, C_BLANC, $e[3], 2);
    rect($s, $x, $y, $cardW, 70, $e[3]);
    txt($s, $e[0], $x, $y + 12, $cardW, 50, 36, C_BLANC, true, Alignment::HORIZONTAL_CENTER);
    txt($s, $e[1], $x + 10, $y + 90, $cardW - 20, 35, 17, C_NOIR, true, Alignment::HORIZONTAL_CENTER);
    txt($s, $e[2], $x + 10, $y + 130, $cardW - 20, 100, 12, C_GRIS, false, Alignment::HORIZONTAL_CENTER);
    $x += $cardW + $gap;
}

txt($s, 'Chaque etape declenche automatiquement la suivante. Le client et l\'agent ASC partagent le meme espace de travail.',
    50, 510, SLIDE_W - 100, 40, 14, C_NOIR, false, Alignment::HORIZONTAL_LEFT, true);
txt($s, 'Documentation : WORKFLOW_PME-CONFORM.docx (8 sections).',
    50, 555, SLIDE_W - 100, 30, 11, C_GRIS, false);

pied($s, 6, $total);

// ============================================================
// SLIDE 7 — ETAPE 1 : MISSION
// ============================================================
$s = $ppt->createSlide();
entete($s, 'Etape 1   —   Creation de la mission', 'Acteur : consultant / manager / admin', C_BLEU_VIF);

txt($s, 'Ecran : /missions  →  Nouvelle mission', 50, 175, SLIDE_W - 100, 30, 14, C_BLEU_VIF, true);

txt($s, 'Champs saisis', 50, 220, 600, 30, 18, C_BLEU, true);
puces($s, [
    'Client (selection dans la liste).',
    'Titre de la mission.',
    'Priorite (basse, normale, haute, urgente).',
    'Methode de travail (M1 ou M2).',
], 50, 260, 600, 240, 15);

txt($s, 'Effets backend', 680, 220, 560, 30, 18, C_ORANGE, true);
puces($s, [
    'Methode 1 : un formulaire vierge est cree automatiquement.',
    'Methode 2 : une matrice 5 poles est creee et pre-remplie.',
    'Statut initial : brouillon.',
    'Reference auto : MISS-AAAA-NNN.',
], 680, 260, 560, 240, 15, C_ORANGE);

rect($s, 50, 540, SLIDE_W - 100, 60, C_BLEU_PALE, C_BLEU_VIF, 1);
txt($s, 'Le champ "Type de mission" a ete retire : il est fixe a audit_conformite.',
    65, 558, SLIDE_W - 130, 40, 13, C_BLEU, true, Alignment::HORIZONTAL_LEFT, true);

pied($s, 7, $total);

// ============================================================
// SLIDE 8 — ETAPE 2 : MATRICE
// ============================================================
$s = $ppt->createSlide();
entete($s, 'Etape 2   —   Matrice de collecte initiale', 'Cote client : ecran /mes-matrices', C_VIOLET);

$poles = [
    ['Pole 1', 'IT, Cyber, Securite', 'PMO, RSSI'],
    ['Pole 2', 'Capital Humain et Administration', 'RH, Admin'],
    ['Pole 3', 'Metiers Assurance et Experience Client', 'Marketing, Commercial'],
    ['Pole 4', 'Controle (Audit et Actuariat)', 'Audit, Conformite'],
    ['Pole 5', 'Finance et Juridique', 'DAF, Juridique'],
];
$y = 175;
foreach ($poles as $p) {
    rect($s, 50, $y, SLIDE_W - 100, 55, C_BLANC, C_VIOLET, 1);
    badge($s, $p[0], 60, $y + 10, 90, 35, C_VIOLET);
    txt($s, $p[1], 165, $y + 14, 700, 30, 16, C_NOIR, true);
    txt($s, 'Cibles : ' . $p[2], 880, $y + 16, 350, 30, 12, C_GRIS, false, Alignment::HORIZONTAL_LEFT, true);
    $y += 65;
}

rect($s, 50, $y + 10, SLIDE_W - 100, 60, C_VIOLET_PALE, C_VIOLET, 1);
txt($s, 'Le client renseigne items + services + postes. Bouton "Generer organigramme" → structure deduite automatiquement.',
    65, $y + 28, SLIDE_W - 130, 40, 13, C_VIOLET, true);

pied($s, 8, $total);

// ============================================================
// SLIDE 9 — ETAPE 3 : ORGANIGRAMME
// ============================================================
$s = $ppt->createSlide();
entete($s, 'Etape 3   —   Organigramme', 'Structure organisationnelle qui pilote la generation IA', C_ORANGE);

carteSection($s, 50, 175, 580, 320, 'Modes de saisie', [
    'Saisie : poles → services → postes (formulaire).',
    'Upload : image, PDF, DOCX, XLSX existants.',
    'Derivation auto depuis la matrice (services/postes).',
], C_BLEU_VIF);

carteSection($s, 660, 175, 580, 320, 'Figeage et generation IA', [
    'Statut "fige" verrouille la structure.',
    'Declenche immediatement la generation IA des questionnaires.',
    '1 questionnaire par pole/service detecte.',
    'Themes auto : biometrie, video, sous-traitance...',
], C_ORANGE);

rect($s, 50, 525, SLIDE_W - 100, 70, C_ORANGE_PALE, C_ORANGE, 1);
txt($s, 'A discuter : faut-il prevoir une regeneration partielle des questionnaires apres modification ponctuelle de l\'organigramme ?',
    65, 545, SLIDE_W - 130, 50, 13, C_ORANGE, true, Alignment::HORIZONTAL_LEFT, true);

pied($s, 9, $total);

// ============================================================
// SLIDE 10 — ETAPE 4 : FORMULAIRES
// ============================================================
$s = $ppt->createSlide();
entete($s, 'Etape 4   —   Formulaires', 'Le client et l\'agent ASC partagent le meme formulaire', C_VERT);

puces($s, [
    'Cote client : menu "Mes formulaires" → liste regroupee par mission.',
    'Cote ASC : section Formulaires sur la fiche mission.',
    'Saisie en mode brouillon ou finalise.',
    'Edition des questions reservee au consultant ASC.',
    'Suppression possible (avec confirmation).',
    'Cas d\'usage cle : interview Google Meet — l\'agent saisit en direct, le client valide.',
], 70, 180, SLIDE_W - 140, 380, 16, C_VERT);

rect($s, 50, 580, SLIDE_W - 100, 60, C_ROUGE_PALE, C_ROUGE, 1);
txt($s, 'Question ouverte : faut-il ajouter une signature electronique sur le formulaire finalise ?',
    65, 598, SLIDE_W - 130, 40, 13, C_ROUGE, true, Alignment::HORIZONTAL_LEFT, true);

pied($s, 10, $total);

// ============================================================
// SLIDE 11 — ETAPE 5 : ANALYSE D'ECARTS
// ============================================================
$s = $ppt->createSlide();
entete($s, 'Etape 5   —   Analyse d\'ecarts', 'Stepper 4 sous-etapes sur /analyses/nouvelle', C_ROUGE);

$flow = [
    ['1', 'Mission', 'Selection (plus de saisie titre/type)', C_BLEU_VIF],
    ['2', 'Sources', 'Documents + formulaires renseignes', C_VIOLET],
    ['3', 'Referentiels', 'Pre-selection selon secteur', C_ORANGE],
    ['4', 'Lancement', 'Mode rapide ou IA enrichi', C_VERT],
];
$x = 50;
$gap = 20;
$cardW = (int)((SLIDE_W - 100 - 3 * $gap) / 4);
$y = 175;
foreach ($flow as $f) {
    rect($s, $x, $y, $cardW, 270, C_BLANC, $f[3], 2);
    rect($s, $x, $y, $cardW, 65, $f[3]);
    txt($s, 'Etape ' . $f[0], $x, $y + 18, $cardW, 30, 14, C_BLANC, true, Alignment::HORIZONTAL_CENTER);
    txt($s, $f[1], $x + 10, $y + 85, $cardW - 20, 30, 18, C_NOIR, true, Alignment::HORIZONTAL_CENTER);
    txt($s, $f[2], $x + 10, $y + 130, $cardW - 20, 100, 12, C_GRIS, false, Alignment::HORIZONTAL_CENTER);
    $x += $cardW + $gap;
}

txt($s, 'Resultat : ecarts horodates par gravite (critique / majeur / mineur), score de conformite, rapport PPTX telechargeable.',
    50, 470, SLIDE_W - 100, 40, 14, C_NOIR);

rect($s, 50, 525, SLIDE_W - 100, 60, C_BLEU_PALE, C_BLEU_VIF, 1);
txt($s, 'Innovation : le moteur transforme les formulaires en chunks RAG, au meme titre que les PDF.',
    65, 543, SLIDE_W - 130, 40, 13, C_BLEU, true, Alignment::HORIZONTAL_LEFT, true);

pied($s, 11, $total);

// ============================================================
// SLIDE 12 — TECHNOLOGIES & SECURITE
// ============================================================
$s = $ppt->createSlide();
entete($s, 'Technologies et securite', 'Stack technique et controles', C_BLEU);

carteSection($s, 50, 175, 580, 380, 'Stack technique', [
    'Backend : Laravel 11 (PHP 8.3) + PostgreSQL + pgvector.',
    'Frontend : React SPA (Vite + Tailwind).',
    'IA locale : Ollama (LLM open source).',
    'Files d\'attente : Laravel Queue (analyses, indexation).',
    'Generation : PhpWord (DOCX) + PhpPresentation (PPTX).',
], C_BLEU_VIF);

carteSection($s, 660, 175, 580, 380, 'Securite et conformite', [
    'Sanctum pour l\'authentification API.',
    'Roles : admin, manager, consultant, client.',
    'Audit log : actions sensibles tracees.',
    'Acces client cloisonne par entreprise.',
    'Donnees confidentielles flagged.',
    'Hash SHA-256 sur chaque document upload.',
], C_ORANGE);

pied($s, 12, $total);

// ============================================================
// SLIDE 13 — KPI
// ============================================================
$s = $ppt->createSlide();
entete($s, 'Indicateurs cles (cible pilote)', 'Mesurer la valeur ajoutee apres 3 missions', C_VERT);

$kpiW = 270;
$kpiH = 180;
$startX = (int)((SLIDE_W - (4 * $kpiW + 3 * 20)) / 2);
kpi($s, '-50%', "Temps de redaction\ndu rapport", $startX, 200, $kpiW, $kpiH, C_BLEU_VIF);
kpi($s, '-70%', "Relances email\nau client", $startX + ($kpiW + 20) * 1, 200, $kpiW, $kpiH, C_ORANGE);
kpi($s, '+30%', "Taux de couverture\ndes referentiels", $startX + ($kpiW + 20) * 2, 200, $kpiW, $kpiH, C_VERT);
kpi($s, '0', "Document\negare", $startX + ($kpiW + 20) * 3, 200, $kpiW, $kpiH, C_VIOLET);

rect($s, 50, 460, SLIDE_W - 100, 90, C_GRIS_LEGER, C_GRIS, 1);
txt($s, 'A challenger ensemble : ces cibles sont-elles realistes ? Faut-il en ajouter d\'autres ?',
    65, 478, SLIDE_W - 130, 30, 14, C_NOIR, true, Alignment::HORIZONTAL_LEFT, true);
txt($s, 'Idees a debattre : NPS client, taux de questionnaires finalises, delai matrice → organigramme, taux d\'ecarts critiques resolus.',
    65, 510, SLIDE_W - 130, 50, 13, C_GRIS, false, Alignment::HORIZONTAL_LEFT, true);

pied($s, 13, $total);

// ============================================================
// SLIDE 14 — ROADMAP
// ============================================================
$s = $ppt->createSlide();
entete($s, 'Roadmap', 'Ce qui est livre, en cours, a venir', C_BLEU_VIF);

$rowH = 130;
$y = 175;

// Livre
rect($s, 50, $y, SLIDE_W - 100, $rowH, C_VERT_PALE, C_VERT, 2);
badge($s, 'Livre', 65, $y + 15, 110, 35, C_VERT);
puces($s, [
    'Mission + matrice 5 poles + organigramme + questionnaires IA.',
    'Espace client (documents, formulaires, matrices).',
    'Moteur d\'analyse RAG (pgvector + plein texte).',
    'Rapport PPTX automatique. Audit log + cloisonnement par client.',
], 200, $y + 15, SLIDE_W - 270, 110, 12, C_VERT);

$y += $rowH + 12;
// En cours
rect($s, 50, $y, SLIDE_W - 100, $rowH, C_ORANGE_PALE, C_ORANGE, 2);
badge($s, 'En cours', 65, $y + 15, 110, 35, C_ORANGE);
puces($s, [
    'Stepper analyses : sources combinees (documents + formulaires).',
    'Derivation organigramme depuis la matrice.',
    'Documentation utilisateur (workflow Word + cette presentation).',
], 200, $y + 15, SLIDE_W - 270, 110, 12, C_ORANGE);

$y += $rowH + 12;
// A venir
rect($s, 50, $y, SLIDE_W - 100, $rowH, C_BLEU_PALE, C_BLEU_VIF, 2);
badge($s, 'A venir', 65, $y + 15, 110, 35, C_BLEU_VIF);
puces($s, [
    'Notifications email (matrice envoyee, formulaire pret, analyse terminee).',
    'Module signature electronique. Tableaux de bord direction.',
    'Connecteur Google Calendar pour planifier les interviews. Mobile-friendly.',
], 200, $y + 15, SLIDE_W - 270, 110, 12, C_BLEU_VIF);

pied($s, 14, $total);

// ============================================================
// SLIDE 15 — ATELIER
// ============================================================
$s = $ppt->createSlide();
entete($s, 'Atelier ouvert   —   vos idees, vos remarques', 'Chacun apporte son regard metier', C_ORANGE);

rect($s, 50, 175, SLIDE_W - 100, 380, C_ORANGE_PALE, C_ORANGE, 2);
txt($s, 'Questions a l\'equipe', 70, 195, SLIDE_W - 140, 35, 20, C_ORANGE, true);
puces($s, [
    'Quels referentiels manquent pour vos missions actuelles ?',
    'Quels champs ajouter dans la matrice 5 poles ?',
    'Quelles fonctions automatiseriez-vous en priorite ?',
    'Quels indicateurs voulez-vous suivre en tant que manager / consultant ?',
    'Quels cas clients reels pourriez-vous nous apporter pour roder le pilote ?',
    'Avez-vous identifie des risques (juridiques, techniques, methodologiques) a discuter ?',
], 70, 240, SLIDE_W - 140, 305, 14, C_ORANGE);

rect($s, 50, 580, SLIDE_W - 100, 60, C_BLEU_PALE, C_BLEU_VIF, 1);
txt($s, 'Format propose : 5 minutes de reflexion individuelle, puis tour de table.',
    50, 598, SLIDE_W - 100, 40, 14, C_BLEU, true, Alignment::HORIZONTAL_CENTER, true);

pied($s, 15, $total);

// ============================================================
// SLIDE 16 — MERCI
// ============================================================
$s = $ppt->createSlide();
$bg = new SlideBgColor();
$bg->setColor(new Color(C_BLEU));
$s->setBackground($bg);

rect($s, 0, 0, SLIDE_W, 18, C_ORANGE);
rect($s, 0, SLIDE_H - 18, SLIDE_W, 18, C_ORANGE);

txt($s, 'Merci !', 0, 200, SLIDE_W, 100, 72, C_BLANC, true, Alignment::HORIZONTAL_CENTER);
rect($s, (int)((SLIDE_W - 160) / 2), 320, 160, 4, C_BLANC);

txt($s, 'PME-CONFORM est notre projet collectif.', 0, 350, SLIDE_W, 40, 20, 'FFBFDBFE', false, Alignment::HORIZONTAL_CENTER, true);
txt($s, 'Vos retours seront integres dans le backlog des deux prochaines semaines.', 0, 395, SLIDE_W, 30, 15, 'FFBFDBFE', false, Alignment::HORIZONTAL_CENTER);

txt($s, 'Canal d\'echange  :  groupe WhatsApp "PME-CONFORM | Core Team"', 0, 470, SLIDE_W, 30, 15, C_BLANC, true, Alignment::HORIZONTAL_CENTER);
txt($s, 'Documentation  :  WORKFLOW_PME-CONFORM.docx', 0, 510, SLIDE_W, 30, 13, 'FFBFDBFE', false, Alignment::HORIZONTAL_CENTER);
txt($s, 'Demos  :  http://localhost:5173', 0, 540, SLIDE_W, 30, 13, 'FFBFDBFE', false, Alignment::HORIZONTAL_CENTER);

// ============================================================
// SAUVEGARDE
// ============================================================
$cible = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'PRESENTATION_PME-CONFORM.pptx';
$writer = IOFactory::createWriter($ppt, 'PowerPoint2007');
try {
    $writer->save($cible);
    echo "Presentation generee : {$cible}\n";
} catch (\Throwable $e) {
    // Probable : fichier ouvert dans PowerPoint -> ecrire sous un nom alternatif
    $alt = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'PRESENTATION_PME-CONFORM_v2.pptx';
    $writer->save($alt);
    echo "ATTENTION : la cible est verrouillee (probablement ouverte dans PowerPoint).\n";
    echo "Presentation generee dans : {$alt}\n";
    echo "Fermez l'ancien fichier puis renommez si besoin.\n";
    $cible = $alt;
}
echo 'Taille : ' . number_format(filesize($cible)) . " octets\n";
