<?php

/**
 * Service RegistreMobisoftExporter — Genere le registre des traitements
 * au format .xlsx en respectant la structure exacte du modele MOBISOFT
 * fourni par AS Consulting (REGISTRE MOBISOFT.xlsx).
 *
 * Le classeur produit contient :
 *   - Feuille « Informations generales » : organisme + RT + DPO + correspondant ASC
 *   - Feuille « Liste des Finalites »    : tableau recapitulatif des fiches
 *   - Feuille « Fiche T1 ... Tn »        : 1 fiche detaillee par traitement valide,
 *                                          structuree comme la fiche modele
 *
 * Les libelles francais (avec accents) reprennent litteralement ceux du modele.
 * Le fichier produit est sauve dans storage/app/registres/.
 */

namespace App\Services\Registre;

use App\Models\Client;
use App\Models\RegistreKyc;
use App\Models\Traitement;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RegistreMobisoftExporter
{
    /** Couleurs MOBISOFT */
    private const COULEUR_BANDEAU = '1F4E78';   // Bleu fonce
    private const COULEUR_SECTION = '2E75B6';   // Bleu intermediaire
    private const COULEUR_SOUS_SECTION = 'D9E1F2'; // Bleu clair
    private const COULEUR_TABLE_HEAD = 'BDD7EE'; // Bleu pale
    private const COULEUR_SENSIBLE = 'FCE4D6';  // Rose : ligne de donnees sensibles

    /** Correspondant externe AS Consulting (pre-rempli sur la feuille Informations). */
    private const AS_CONSULTING = [
        'nom' => 'AS CONSULTING',
        'adresse' => "Cocody, Angre 8eme Tranche",
        'code_postal' => '02 BP 1245 Abidjan 02.',
        'ville' => 'ABIDJAN',
        'pays' => "Cote d'Ivoire",
        'telephone' => '',
        'email' => 'info@asconsulting.ci',
    ];

    public function generer(Client $client, User $initiateur): RegistreKyc
    {
        $client->load('organisme');
        $traitements = $client->traitements()
            ->valides()
            ->with(['supports', 'actes', 'personnes', 'categoriesDonnees', 'transferts', 'mesuresSecurite'])
            ->orderBy('reference')
            ->get();

        $reference = $this->genererReference();
        $registre = RegistreKyc::create([
            'client_id' => $client->id,
            'genere_par' => $initiateur->id,
            'reference' => $reference,
            'nb_traitements' => $traitements->count(),
            'snapshot_traitements' => $traitements->map(fn (Traitement $t) => [
                'id' => $t->id,
                'reference' => $t->reference,
                'designation' => $t->designation,
            ])->values()->all(),
            'fichier_path' => '',
            'hash_fichier' => '',
            'format' => 'xlsx',
            'statut_generation' => 'en_cours',
        ]);

        try {
            $ss = new Spreadsheet();
            $ss->removeSheetByIndex(0);

            $this->ecrireFeuilleCste($ss);
            $this->ecrireFeuilleInformations($ss, $client);
            $this->ecrireFeuilleListe($ss, $traitements);
            foreach ($traitements as $i => $t) {
                $this->ecrireFicheTraitement($ss, $t, $i + 1);
            }

            $ss->setActiveSheetIndex(0);

            $cheminRelatif = "registres/{$reference}.xlsx";
            $cheminAbsolu = Storage::disk('local')->path($cheminRelatif);
            if (! is_dir(dirname($cheminAbsolu))) {
                mkdir(dirname($cheminAbsolu), 0755, true);
            }

            $writer = IOFactory::createWriter($ss, 'Xlsx');
            $writer->save($cheminAbsolu);

            $registre->update([
                'fichier_path' => $cheminRelatif,
                'hash_fichier' => hash_file('sha256', $cheminAbsolu),
                'statut_generation' => 'termine',
            ]);
        } catch (\Throwable $e) {
            $registre->update([
                'statut_generation' => 'erreur',
                'erreur_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $registre->fresh();
    }

    // ------------------------------------------------------------------
    // FEUILLE 0 : CSTE — Listes de reference (periodes + pays)
    // Utilisable comme source de validation de donnees Excel sur les autres
    // feuilles (ex: durees de conservation, pays de transfert).
    // ------------------------------------------------------------------
    private function ecrireFeuilleCste(Spreadsheet $ss): void
    {
        $s = $ss->createSheet();
        $s->setTitle('Cste');

        // En-tetes
        $s->setCellValue('A1', 'Nombre');
        $s->setCellValue('B1', 'Période');
        $s->setCellValue('C1', '');
        $s->setCellValue('D1', 'Pays');
        $s->getStyle('A1:D1')->getFont()->setBold(true);
        $this->fond($s, 'A1:D1', self::COULEUR_TABLE_HEAD);

        // Periodes (col B) — 4 valeurs canoniques
        $periodes = ['Jours', 'Semaines', 'Mois', 'Années'];
        foreach ($periodes as $i => $p) {
            $s->setCellValue('B' . ($i + 2), $p);
        }

        // Pays (col D) — liste alphabetique
        $pays = $this->listePays();
        foreach ($pays as $i => $p) {
            $r = $i + 2;
            $s->setCellValue("A{$r}", $i + 1);
            $s->setCellValue("D{$r}", $p);
        }

        // Largeurs
        $s->getColumnDimension('A')->setWidth(10);
        $s->getColumnDimension('B')->setWidth(14);
        $s->getColumnDimension('C')->setWidth(4);
        $s->getColumnDimension('D')->setWidth(32);

        // Masquer la feuille (utile comme source de validation mais pas pour la
        // lecture humaine du registre). Decommenter si vous voulez la cacher :
        // $s->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    }

    /**
     * Liste alphabétique des pays (réutilisable pour validation des transferts
     * hors CEDEAO). Source : nomenclature ISO 3166 — noms officiels en français.
     */
    private function listePays(): array
    {
        return [
            'Afghanistan', 'Afrique du Sud', 'Albanie', 'Algérie', 'Allemagne', 'Andorre',
            'Angola', 'Antigua-et-Barbuda', 'Arabie saoudite', 'Argentine', 'Arménie',
            'Australie', 'Autriche', 'Azerbaïdjan', 'Bahamas', 'Bahreïn', 'Bangladesh',
            'Barbade', 'Belgique', 'Belize', 'Bénin', 'Bermudes', 'Bhoutan', 'Biélorussie',
            'Birmanie', 'Bolivie', 'Bosnie-Herzégovine', 'Botswana', 'Brésil', 'Brunei',
            'Bulgarie', 'Burkina Faso', 'Burundi', 'Cambodge', 'Cameroun', 'Canada',
            'Cap-Vert', 'Chili', 'Chine', 'Chypre', 'Colombie', 'Comores', 'Congo',
            'Corée du Nord', 'Corée du Sud', 'Costa Rica', "Côte d'Ivoire", 'Croatie',
            'Cuba', 'Danemark', 'Djibouti', 'Dominique', 'Égypte', 'Émirats arabes unis',
            'Équateur', 'Érythrée', 'Espagne', 'Estonie', 'États-Unis', 'Éthiopie',
            'Fidji', 'Finlande', 'France', 'Gabon', 'Gambie', 'Géorgie', 'Ghana',
            'Gibraltar', 'Grèce', 'Grenade', 'Groenland', 'Guatemala', 'Guernesey',
            'Guinée', 'Guinée équatoriale', 'Guinée-Bissau', 'Guyana', 'Haïti',
            'Honduras', 'Hong-Kong', 'Hongrie', 'Île de Man', 'Îles Féroé', 'Inde',
            'Indonésie', 'Iran', 'Iraq', 'Irlande', 'Islande', 'Israël', 'Italie',
            'Jamaïque', 'Japon', 'Jersey', 'Jordanie', 'Kazakhstan', 'Kenya',
            'Kirghizistan', 'Kiribati', 'Kosovo', 'Koweït', 'Laos', 'Lesotho',
            'Lettonie', 'Liban', 'Libéria', 'Libye', 'Liechtenstein', 'Lituanie',
            'Luxembourg', 'Macao', 'Macédoine du Nord', 'Madagascar', 'Malaisie',
            'Malawi', 'Maldives', 'Mali', 'Malte', 'Maroc', 'Maurice', 'Mauritanie',
            'Mexique', 'Micronésie', 'Moldavie', 'Monaco', 'Mongolie', 'Monténégro',
            'Mozambique', 'Namibie', 'Nauru', 'Népal', 'Nicaragua', 'Niger', 'Nigéria',
            'Norvège', 'Nouvelle-Zélande', 'Oman', 'Ouganda', 'Ouzbékistan', 'Pakistan',
            'Palaos', 'Palestine', 'Panama', 'Papouasie-Nouvelle-Guinée', 'Paraguay',
            'Pays-Bas', 'Pérou', 'Philippines', 'Pologne', 'Portugal', 'Qatar',
            'République centrafricaine', 'République dominicaine', 'République tchèque',
            'République démocratique du Congo', 'Roumanie', 'Royaume-Uni', 'Russie',
            'Rwanda', 'Saint-Kitts-et-Nevis', 'Saint-Marin', 'Saint-Vincent-et-les-Grenadines',
            'Sainte-Lucie', 'Salomon', 'Salvador', 'Samoa', 'São Tomé-et-Principe',
            'Sénégal', 'Serbie', 'Seychelles', 'Sierra Leone', 'Singapour', 'Slovaquie',
            'Slovénie', 'Somalie', 'Soudan', 'Soudan du Sud', 'Sri Lanka', 'Suède',
            'Suisse', 'Suriname', 'Syrie', 'Tadjikistan', 'Taïwan', 'Tanzanie', 'Tchad',
            'Thaïlande', 'Timor oriental', 'Togo', 'Tonga', 'Trinité-et-Tobago',
            'Tunisie', 'Turkménistan', 'Turquie', 'Tuvalu', 'Ukraine', 'Uruguay',
            'Vanuatu', 'Vatican', 'Venezuela', 'Vietnam', 'Yémen', 'Zambie', 'Zimbabwe',
        ];
    }

    // ------------------------------------------------------------------
    // FEUILLE 1 : INFORMATIONS GENERALES
    // ------------------------------------------------------------------
    private function ecrireFeuilleInformations(Spreadsheet $ss, Client $client): void
    {
        $s = $ss->createSheet();
        $s->setTitle('Informations générales');
        $org = $client->organisme;

        // Bandeau titre
        $s->setCellValue('A1', 'REGISTRE DES ACTIVITÉS DE TRAITEMENT DE ');
        $s->mergeCells('A1:E1');
        $this->styleBandeau($s, 'A1:E1');
        $s->getRowDimension(1)->setRowHeight(28);

        $s->setCellValue('A2', mb_strtoupper($client->raison_sociale ?? ''));
        $s->mergeCells('A2:E2');
        $s->getStyle('A2')->getFont()->setBold(true)->setSize(13);
        $s->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $this->fond($s, 'A2:E2', self::COULEUR_SOUS_SECTION);
        $s->getRowDimension(2)->setRowHeight(22);

        // Bloc 1 : Coordonnees de l'organisme
        $r = 4;
        $this->titreBloc($s, "A{$r}:E{$r}", "Coordonnées de l'organisme");
        $r++;
        $this->lignesContact($s, $r, [
            'Nom' => $client->raison_sociale,
            'Adresse' => $client->adresse,
            'CP' => null,
            'Ville' => $client->ville,
            'Telephone' => $client->telephone,
            'Mail' => $client->email,
        ]);
        $r += 4;

        // Bloc 2 : Coordonnees du responsable de l'organisme
        $r += 1;
        $this->titreBloc($s, "A{$r}:E{$r}", "Coordonnées du responsable de l'organisme");
        $r++;
        $this->lignesContact($s, $r, [
            'Nom' => $org?->rt_nom,
            'Adresse' => $org?->rt_adresse,
            'CP' => $org?->rt_code_postal,
            'Ville' => $org?->rt_ville,
            'Telephone' => $org?->rt_telephone,
            'Mail' => $org?->rt_email,
        ]);
        $r += 4;

        // Bloc 3 : Delegue a la protection des donnees (DPO)
        $r += 1;
        $this->titreBloc($s, "A{$r}:E{$r}", "Nom et coordonnées du délégué à la protection des données");
        $r++;
        $this->lignesContact($s, $r, [
            'Nom' => $org?->dpo_nom,
            'Adresse' => $org?->dpo_adresse,
            'CP' => $org?->dpo_code_postal,
            'Ville' => $org?->dpo_ville,
            'Telephone' => $org?->dpo_telephone,
            'Mail' => $org?->dpo_email,
        ]);
        $r += 4;

        // Bloc 4 : Correspondant externe AS Consulting (constante)
        $r += 1;
        $this->titreBloc($s, "A{$r}:E{$r}", "Nom et coordonnées du correspondant à la protection des données (Externe)");
        $r++;
        $this->lignesContact($s, $r, [
            'Nom' => self::AS_CONSULTING['nom'],
            'Adresse' => self::AS_CONSULTING['adresse'],
            'CP' => self::AS_CONSULTING['code_postal'],
            'Ville' => self::AS_CONSULTING['ville'],
            'Telephone' => self::AS_CONSULTING['telephone'],
            'Mail' => self::AS_CONSULTING['email'],
        ]);

        // Largeurs de colonnes
        $s->getColumnDimension('A')->setWidth(6);
        $s->getColumnDimension('B')->setWidth(18);
        $s->getColumnDimension('C')->setWidth(38);
        $s->getColumnDimension('D')->setWidth(12);
        $s->getColumnDimension('E')->setWidth(28);
    }

    /**
     * Pose 4 lignes (Nom/Adresse | CP/Ville | Telephone/Mail) a partir de la ligne $r.
     * Layout colle au modele : col B = label, col C = valeur ;
     * col D = label, col E = valeur (utilisee pour Ville et Mail).
     */
    private function lignesContact(Worksheet $s, int $r, array $valeurs): void
    {
        // Ligne 1 : Nom
        $s->setCellValue("B{$r}", 'Nom :');
        $s->setCellValue("C{$r}", $valeurs['Nom']);
        $s->mergeCells("C{$r}:E{$r}");

        // Ligne 2 : Adresse
        $s->setCellValue("B" . ($r + 1), 'Adresse :');
        $s->setCellValue("C" . ($r + 1), $valeurs['Adresse']);
        $s->mergeCells("C" . ($r + 1) . ":E" . ($r + 1));

        // Ligne 3 : CP + Ville
        $s->setCellValue("B" . ($r + 2), 'CP :');
        $s->setCellValue("C" . ($r + 2), $valeurs['CP']);
        $s->setCellValue("D" . ($r + 2), 'Ville :');
        $s->setCellValue("E" . ($r + 2), $valeurs['Ville']);

        // Ligne 4 : Telephone + Mail
        $s->setCellValue("B" . ($r + 3), 'Téléphone :');
        $s->setCellValue("C" . ($r + 3), $valeurs['Telephone']);
        $s->setCellValue("D" . ($r + 3), 'Mail :');
        $s->setCellValue("E" . ($r + 3), $valeurs['Mail']);

        // Bordures + style labels
        $s->getStyle("B{$r}:E" . ($r + 3))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        foreach (range($r, $r + 3) as $rr) {
            $s->getStyle("B{$rr}")->getFont()->setBold(true);
            $s->getStyle("D{$rr}")->getFont()->setBold(true);
        }
    }

    // ------------------------------------------------------------------
    // FEUILLE 2 : LISTE DES FINALITES
    // ------------------------------------------------------------------
    private function ecrireFeuilleListe(Spreadsheet $ss, $traitements): void
    {
        $s = $ss->createSheet();
        $s->setTitle('Liste des Finalités');

        $headers = [
            '#',
            'Désignation de la finalité',
            'Code de Finalité',
            'Liste des Activités',
            'Direction',
            'Date de Création',
            'Date de mise à Jour',
            'Données Sensibles',
            'Transfert',
        ];
        $colonnes = ['A','B','C','D','E','F','G','H','I'];

        foreach ($headers as $i => $h) {
            $s->setCellValue("{$colonnes[$i]}1", $h);
        }
        $s->getStyle("A1:I1")->getFont()->setBold(true);
        $s->getStyle("A1:I1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        $this->fond($s, 'A1:I1', self::COULEUR_TABLE_HEAD);
        $s->getRowDimension(1)->setRowHeight(32);

        foreach ($traitements as $i => $t) {
            $r = $i + 2;
            $s->setCellValue("A{$r}", $i + 1);
            $s->setCellValue("B{$r}", $t->designation);
            $s->setCellValue("C{$r}", $t->code_finalite ?: $t->reference);
            $s->setCellValue("D{$r}", $this->formatListe($t->actes->pluck('acte')->all()));
            $s->setCellValue("E{$r}", $t->direction_pole);
            $s->setCellValue("F{$r}", $t->date_creation_fiche?->format('d/m/Y'));
            $s->setCellValue("G{$r}", $t->date_maj_fiche?->format('d/m/Y'));
            $s->setCellValue("H{$r}", $t->contient_donnees_sensibles ? 'Oui' : 'Non');
            $s->setCellValue("I{$r}", $t->transfert_hors_cedeao ? 'Oui' : 'Non');

            // Mise en evidence des traitements a risque
            if ($t->contient_donnees_sensibles) {
                $this->fond($s, "H{$r}", self::COULEUR_SENSIBLE);
                $s->getStyle("H{$r}")->getFont()->setBold(true);
            }
            if ($t->transfert_hors_cedeao) {
                $this->fond($s, "I{$r}", self::COULEUR_SENSIBLE);
                $s->getStyle("I{$r}")->getFont()->setBold(true);
            }
        }

        $derniereLigne = max(1, count($traitements) + 1);
        $s->getStyle("A1:I{$derniereLigne}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $s->getStyle("A2:A{$derniereLigne}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $s->getStyle("D2:D{$derniereLigne}")->getAlignment()->setWrapText(true);
        $s->getStyle("F2:I{$derniereLigne}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $largeurs = ['A' => 5, 'B' => 36, 'C' => 14, 'D' => 38, 'E' => 22, 'F' => 14, 'G' => 14, 'H' => 14, 'I' => 14];
        foreach ($largeurs as $col => $w) {
            $s->getColumnDimension($col)->setWidth($w);
        }
    }

    // ------------------------------------------------------------------
    // FEUILLE N : FICHE D'UN TRAITEMENT
    // ------------------------------------------------------------------
    private function ecrireFicheTraitement(Spreadsheet $ss, Traitement $t, int $index): void
    {
        $s = $ss->createSheet();
        $s->setTitle("Fiche T{$index}");

        // Designation + code (haut de fiche)
        $r = 1;
        $s->setCellValue("A{$r}", $t->designation);
        $s->mergeCells("A{$r}:G{$r}");
        $s->setCellValue("H{$r}", $t->code_finalite ?: $t->reference);
        $s->getStyle("A{$r}")->getFont()->setBold(true)->setSize(13);
        $s->getStyle("H{$r}")->getFont()->setBold(true)->getColor()->setRGB('1F4E78');
        $s->getStyle("H{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $s->getRowDimension($r)->setRowHeight(28);
        $r += 2;

        // Description de la finalite
        $this->bandeauSection($s, "A{$r}:H{$r}", 'DESCRIPTION DE LA FINALITÉ');
        $r++;
        $s->setCellValue("A{$r}", $t->description);
        $s->mergeCells("A{$r}:H{$r}");
        $s->getStyle("A{$r}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $s->getRowDimension($r)->setRowHeight(60);
        $r += 2;

        // Dates et services
        $s->setCellValue("A{$r}", 'Date de création de la fiche');
        $s->setCellValue("C{$r}", $t->date_creation_fiche?->format('d/m/Y'));
        $r++;
        $s->setCellValue("A{$r}", 'Date de mise à jour de la fiche');
        $s->setCellValue("C{$r}", $t->date_maj_fiche?->format('d/m/Y'));
        $r++;
        $s->setCellValue("A{$r}", "Service(s) chargé(s) de la mise en œuvre du traitement");
        $s->setCellValue("C{$r}", $this->formatListe($t->services_charges));
        $s->mergeCells("C{$r}:E{$r}");
        $s->setCellValue("F{$r}", 'Source(s)');
        $s->setCellValue("G{$r}", $this->formatListe($t->sources));
        $s->mergeCells("G{$r}:H{$r}");
        $r++;
        $s->setCellValue("A{$r}", 'Direction / Pôle');
        $s->setCellValue("C{$r}", $t->direction_pole);
        $r += 2;

        // Supports du traitement
        $this->bandeauSection($s, "A{$r}:H{$r}", 'SUPPORTS DU TRAITEMENT');
        $r++;
        $r = $this->ecrireCatalogueSupports($s, $r, $t);
        $r++;

        // Traitement (actes) + Base legale (cote a cote)
        $this->bandeauSection($s, "A{$r}:F{$r}", 'TRAITEMENT');
        $this->bandeauSection($s, "G{$r}:H{$r}", 'BASE LÉGALE');
        $r++;
        if ($t->actes->isEmpty()) {
            $s->setCellValue("A{$r}", '');
            $r++;
        } else {
            foreach ($t->actes as $a) {
                $s->setCellValue("A{$r}", $a->acte);
                $s->mergeCells("A{$r}:F{$r}");
                $s->setCellValue("G{$r}", $a->base_legale . ($a->precision ? "\n" . $a->precision : ''));
                $s->mergeCells("G{$r}:H{$r}");
                $s->getStyle("A{$r}:H{$r}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                $s->getRowDimension($r)->setRowHeight(28);
                $r++;
            }
        }
        $r++;

        // Categories de personnes concernees + Documentation source
        $this->bandeauSection($s, "A{$r}:E{$r}", 'CATÉGORIE DE PERSONNES CONCERNÉES');
        $this->bandeauSection($s, "F{$r}:H{$r}", 'DOCUMENTATION(S) SOURCE(S)');
        $r++;
        if ($t->personnes->isEmpty()) {
            $r++;
        } else {
            foreach ($t->personnes as $p) {
                $s->setCellValue("A{$r}", $p->categorie);
                $s->mergeCells("A{$r}:E{$r}");
                $s->setCellValue("F{$r}", $p->documentation_source);
                $s->mergeCells("F{$r}:H{$r}");
                $s->getStyle("A{$r}:H{$r}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                $r++;
            }
        }
        $r++;

        // Categories de donnees concernees
        $this->bandeauSection($s, "A{$r}:H{$r}", 'CATÉGORIE DE DONNÉES CONCERNÉES');
        $r++;
        $r = $this->ecrireCatalogueDonnees($s, $r, $t);
        $r++;

        // Transferts hors CEDEAO
        $this->bandeauSection($s, "A{$r}:H{$r}", 'TRANSFERT DE DONNÉES HORS CEDEAO');
        $r++;
        $this->enteteTableau($s, $r, [
            'A' => 'Organe',
            'C' => 'Pays',
            'D' => 'Garantie',
            'G' => 'Sens-Groupe',
        ]);
        $s->mergeCells("A{$r}:B{$r}");
        $s->mergeCells("D{$r}:F{$r}");
        $s->mergeCells("G{$r}:H{$r}");
        $r++;
        if ($t->transferts->isEmpty()) {
            $s->setCellValue("A{$r}", 'Aucun transfert hors CEDEAO');
            $s->mergeCells("A{$r}:H{$r}");
            $s->getStyle("A{$r}")->getFont()->setItalic(true)->getColor()->setRGB('808080');
            $r++;
        } else {
            foreach ($t->transferts as $tr) {
                $s->setCellValue("A{$r}", $tr->organe);
                $s->mergeCells("A{$r}:B{$r}");
                $s->setCellValue("C{$r}", $tr->pays);
                $s->setCellValue("D{$r}", $tr->garantie);
                $s->mergeCells("D{$r}:F{$r}");
                $s->setCellValue("G{$r}", $tr->sens_groupe);
                $s->mergeCells("G{$r}:H{$r}");
                $s->getStyle("A{$r}:H{$r}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                $r++;
            }
        }
        $r++;

        // Mesures de securite : on AFFICHE TOUJOURS les 7 blocs (memes vides)
        // pour calquer la fiche modele MOBISOFT et permettre a l'auditeur de
        // visualiser ce qui a ete renseigne / ce qui manque.
        $this->bandeauSection($s, "A{$r}:H{$r}", 'MESURES DE SÉCURITÉ');
        $r++;
        foreach ($this->categoriesMesures() as $cat => $libelle) {
            $mesures = $t->mesuresSecurite->where('categorie', $cat);

            // En-tete du bloc (toujours affiche)
            $s->setCellValue("A{$r}", $libelle);
            $s->mergeCells("A{$r}:H{$r}");
            $s->getStyle("A{$r}")->getFont()->setBold(true);
            $this->fond($s, "A{$r}:H{$r}", self::COULEUR_SOUS_SECTION);
            $r++;

            if ($mesures->isEmpty()) {
                // Bloc vide : on laisse une ligne grisee pour signaler que
                // l'utilisateur n'a rien renseigne dans cette categorie.
                $s->setCellValue("A{$r}", "(Aucune mesure renseignée)");
                $s->mergeCells("A{$r}:H{$r}");
                $s->getStyle("A{$r}")->getFont()->setItalic(true)->getColor()->setRGB('A0A0A0');
                $s->getStyle("A{$r}")->getAlignment()->setIndent(1);
                $r++;
            } else {
                foreach ($mesures as $m) {
                    $s->setCellValue("A{$r}", '• ' . $m->description);
                    $s->mergeCells("A{$r}:H{$r}");
                    $s->getStyle("A{$r}")->getAlignment()->setWrapText(true)->setIndent(1);
                    $r++;
                }
            }
        }

        // Largeurs et bordures globales
        $largeurs = ['A' => 22, 'B' => 14, 'C' => 14, 'D' => 14, 'E' => 14, 'F' => 14, 'G' => 14, 'H' => 14];
        foreach ($largeurs as $c => $w) {
            $s->getColumnDimension($c)->setWidth($w);
        }
        $s->getStyle("A1:H{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('CCCCCC');
    }

    // ------------------------------------------------------------------
    // Helpers de style
    // ------------------------------------------------------------------
    private function styleBandeau(Worksheet $s, string $range): void
    {
        $s->getStyle($range)->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('FFFFFF');
        $s->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $this->fond($s, $range, self::COULEUR_BANDEAU);
    }

    private function titreBloc(Worksheet $s, string $range, string $texte): void
    {
        $cellule = explode(':', $range)[0];
        $s->setCellValue($cellule, $texte);
        $s->mergeCells($range);
        $s->getStyle($range)->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('FFFFFF');
        $s->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setIndent(1);
        $this->fond($s, $range, self::COULEUR_SECTION);
    }

    private function bandeauSection(Worksheet $s, string $range, string $texte): void
    {
        $cellule = explode(':', $range)[0];
        $s->setCellValue($cellule, $texte);
        $s->mergeCells($range);
        $s->getStyle($range)->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('FFFFFF');
        $s->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $this->fond($s, $range, self::COULEUR_SECTION);
    }

    private function enteteTableau(Worksheet $s, int $r, array $colsLabels): void
    {
        foreach ($colsLabels as $col => $label) {
            $s->setCellValue("{$col}{$r}", $label);
        }
        $colonnes = array_keys($colsLabels);
        $debut = reset($colonnes);
        $fin = 'H';
        $s->getStyle("{$debut}{$r}:{$fin}{$r}")->getFont()->setBold(true);
        $s->getStyle("{$debut}{$r}:{$fin}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        $this->fond($s, "{$debut}{$r}:{$fin}{$r}", self::COULEUR_TABLE_HEAD);
    }

    private function fond(Worksheet $s, string $range, string $rgb): void
    {
        $s->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB($rgb);
    }

    private function categoriesMesures(): array
    {
        return [
            'controle_acces' => "Contrôle d'accès des utilisateurs",
            'tracabilite' => 'Mesures de traçabilité',
            'protection_logiciels' => 'Mesures de protection des logiciels',
            'sauvegarde' => 'Sauvegarde des données',
            'chiffrement' => 'Chiffrement des données',
            'controle_sous_traitants' => 'Contrôle des sous-traitants',
            'autres' => 'Autres mesures',
        ];
    }

    /**
     * Catalogue des sous-types de supports pré-listés dans la fiche MOBISOFT.
     */
    private function catalogueSupports(): array
    {
        return [
            'materiel' => [
                'titre' => 'Matériels',
                'items' => ['Ordinateur Portable', 'Ordinateur Fixe', 'Tablette', 'Téléphone', 'Autres'],
            ],
            'logiciel' => [
                'titre' => 'Logiciel',
                'items' => ['Logiciel Métier', 'Logiciel Bureautique', 'Application', 'Autres (Précisez)'],
            ],
            'papier' => [
                'titre' => 'Papier',
                'items' => ['Registre', 'Papier (simple)', 'Papier (Préimprimé)', 'Autres (Précisez)'],
            ],
        ];
    }

    /**
     * Pose le bloc SUPPORTS DU TRAITEMENT sous forme de catalogue pré-listé :
     * chaque sous-type apparaît sur sa propre ligne ; la marque/version et la
     * précision sont remplies si l'utilisateur a saisi un support qui matche
     * (par catégorie + libellé du type, case-insensitive). Les supports saisis
     * hors catalogue tombent dans une rubrique "Autres" en fin de section.
     */
    private function ecrireCatalogueSupports(Worksheet $s, int $r, Traitement $t): int
    {
        $this->enteteTableau($s, $r, [
            'A' => 'Catégorie de support',
            'B' => 'Type',
            'D' => 'Marque / Version',
            'F' => 'Précision',
        ]);
        $s->mergeCells("B{$r}:C{$r}");
        $s->mergeCells("D{$r}:E{$r}");
        $s->mergeCells("F{$r}:H{$r}");
        $r++;

        // Index des supports saisis : clé = "categorie::type" (lowercase)
        $saisis = [];
        foreach ($t->supports as $sup) {
            $cle = mb_strtolower(trim($sup->categorie . '::' . $sup->type));
            $saisis[$cle] = $sup;
        }

        foreach ($this->catalogueSupports() as $cat => $bloc) {
            $premier = true;
            foreach ($bloc['items'] as $type) {
                $cle = mb_strtolower($cat . '::' . $type);
                $sup = $saisis[$cle] ?? null;

                $s->setCellValue("A{$r}", $premier ? $bloc['titre'] : '');
                $s->setCellValue("B{$r}", $type);
                $s->mergeCells("B{$r}:C{$r}");
                $s->setCellValue("D{$r}", $sup?->marque_version ?? '');
                $s->mergeCells("D{$r}:E{$r}");
                $s->setCellValue("F{$r}", $sup?->precision ?? '');
                $s->mergeCells("F{$r}:H{$r}");
                $s->getStyle("A{$r}:H{$r}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                unset($saisis[$cle]);
                $premier = false;
                $r++;
            }
        }

        // Items saisis hors catalogue
        if (! empty($saisis)) {
            $s->setCellValue("A{$r}", 'Autres (saisis par l\'utilisateur)');
            $s->getStyle("A{$r}")->getFont()->setItalic(true);
            $r++;
            foreach ($saisis as $sup) {
                $s->setCellValue("A{$r}", '');
                $s->setCellValue("B{$r}", trim($sup->categorie . ' / ' . $sup->type));
                $s->mergeCells("B{$r}:C{$r}");
                $s->setCellValue("D{$r}", $sup->marque_version);
                $s->mergeCells("D{$r}:E{$r}");
                $s->setCellValue("F{$r}", $sup->precision);
                $s->mergeCells("F{$r}:H{$r}");
                $r++;
            }
        }

        return $r;
    }

    /**
     * Catalogue des catégories de données personnelles, calqué sur la fiche
     * MOBISOFT. L'utilisateur "coche" via les lignes saisies dans
     * Traitement.categoriesDonnees ; on rapproche par libellé (case-insensitive)
     * pour marquer "X" dans la colonne Direct ou Indirect.
     */
    private function catalogueDonnees(): array
    {
        return [
            // Catégories ordinaires
            [
                'titre' => 'État-civil, Identité, Données d\'identification',
                'sensible' => false,
                'items' => [
                    'Nom et Prénom', 'Âge', 'Date de naissance', 'Lieu de naissance',
                    'Genre (M/F)', 'Adresse postale', 'Adresse Fiscale', 'Adresse mail',
                    'Photographie', 'Signature', 'Nationalité',
                ],
            ],
            [
                'titre' => 'Vie personnelle',
                'sensible' => false,
                'items' => ['Habitude de vie', 'Situation familiale', 'Nombre d\'enfants'],
            ],
            [
                'titre' => 'Vie professionnelle',
                'sensible' => false,
                'items' => [
                    'Date d\'embauche', 'Situation Professionnelle', 'Curriculum Vitae (CV)',
                    'Scolarité', 'Formation Distinction', 'Numéro matricule',
                ],
            ],
            [
                'titre' => 'Informations d\'ordre économique et financier',
                'sensible' => false,
                'items' => ['Revenus', 'Salaire', 'Situation financière', 'Relevé d\'identité bancaire (RIB)'],
            ],
            [
                'titre' => 'Données de connexion (Adresse IP, logs, etc.)',
                'sensible' => false,
                'items' => ['Identifiants des terminaux', 'Identifiants de connexions', 'Information d\'horodatage'],
            ],
            [
                'titre' => 'Données de localisation (déplacements, données GPS, GSM, etc.)',
                'sensible' => false,
                'items' => [
                    'Localisation par satellite', 'Localisation par whatsapp',
                    'Localisation par téléphone mobile', 'Données GPS collectées de façon directe ou autre',
                ],
            ],
            // Catégories particulières (souvent sensibles)
            [
                'titre' => 'Numéro national d\'identification / (ou autre identifiant de la même nature)',
                'sensible' => false,
                'items' => [
                    'Numéro téléphone', 'Carte nationale d\'identité (CNI)', 'Passeport',
                    'Titre de séjour', 'Permis de conduire', 'Numéro de sécurité sociale',
                    'Numéro CMU', 'Numéro extrait de naissance',
                ],
            ],
            [
                'titre' => 'Données biométriques',
                'sensible' => true,
                'items' => [
                    'Contour de la main', 'Empreintes digitales', 'Reconnaissance vocale',
                    'Reconnaissance faciale', 'Iris de l\'œil',
                ],
            ],
            [
                'titre' => 'Données de santé (Données sensibles)',
                'sensible' => true,
                'items' => ['Pathologie', 'Affection', 'Antécédents familiaux', 'Données relatives aux soins'],
            ],
            [
                'titre' => 'Autres données sensibles (Données sensibles)',
                'sensible' => true,
                'items' => [
                    'Origines raciales ou ethniques', 'Opinions politiques', 'Opinions religieuses',
                    'Appartenance syndicale', 'Vie sexuelle',
                ],
            ],
            [
                'titre' => 'Infractions, condamnations, mesures de sûreté (Données sensibles)',
                'sensible' => true,
                'items' => ['Infractions', 'Condamnations', 'Mesures de sûreté'],
            ],
            [
                'titre' => 'Appréciation sur les difficultés sociales des personnes (Données sensibles)',
                'sensible' => true,
                'items' => ['Précisez :'],
            ],
        ];
    }

    /**
     * Pose le bloc CATÉGORIE DE DONNÉES sous forme de catalogue pré-listé
     * (toutes les sous-catégories sont affichées en lignes ; X dans Direct ou
     * Indirect si l'utilisateur a saisi l'item, vide sinon).
     */
    private function ecrireCatalogueDonnees(Worksheet $s, int $r, Traitement $t): int
    {
        // En-tête du tableau
        $this->enteteTableau($s, $r, [
            'A' => 'Catégorie de données',
            'B' => 'Détail',
            'D' => 'Origine - Direct',
            'E' => 'Origine - Indirect',
            'F' => 'Sensible',
        ]);
        $s->mergeCells("B{$r}:C{$r}");
        $s->mergeCells("F{$r}:H{$r}");
        $r++;

        // Index des items saisis par l'utilisateur : clé = nom du détail en minuscule.
        $saisis = [];
        foreach ($t->categoriesDonnees as $d) {
            $cle = mb_strtolower(trim((string) $d->detail));
            if ($cle === '') continue;
            $saisis[$cle] = $d;
        }

        foreach ($this->catalogueDonnees() as $bloc) {
            $titre = $bloc['titre'];
            $sensible = $bloc['sensible'];
            $premier = true;

            foreach ($bloc['items'] as $item) {
                $s->setCellValue("A{$r}", $premier ? $titre : '');
                $s->setCellValue("B{$r}", $item);
                $s->mergeCells("B{$r}:C{$r}");

                $cle = mb_strtolower($item);
                $saisi = $saisis[$cle] ?? null;
                $direct = $saisi && $saisi->origine === 'direct' ? 'X' : '';
                $indirect = $saisi && $saisi->origine === 'indirect' ? 'X' : '';
                $estSensible = $sensible || ($saisi && $saisi->est_sensible);

                $s->setCellValue("D{$r}", $direct);
                $s->setCellValue("E{$r}", $indirect);
                $s->setCellValue("F{$r}", $estSensible ? 'Oui' : '');
                $s->mergeCells("F{$r}:H{$r}");
                $s->getStyle("D{$r}:H{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                if ($estSensible) {
                    $this->fond($s, "A{$r}:H{$r}", self::COULEUR_SENSIBLE);
                }
                $s->getStyle("A{$r}:H{$r}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                $premier = false;
                $r++;
            }
        }

        // Items saisis par l'utilisateur qui ne sont PAS dans le catalogue :
        // on les ajoute en fin sous une rubrique "Autres (saisis par l'utilisateur)".
        $libellesCatalogue = [];
        foreach ($this->catalogueDonnees() as $bloc) {
            foreach ($bloc['items'] as $item) {
                $libellesCatalogue[mb_strtolower($item)] = true;
            }
        }
        $custom = collect($saisis)->filter(fn ($d, $cle) => ! isset($libellesCatalogue[$cle]))->values();

        if ($custom->isNotEmpty()) {
            $premier = true;
            foreach ($custom as $d) {
                $s->setCellValue("A{$r}", $premier ? 'Autres (saisis par l\'utilisateur)' : '');
                $s->setCellValue("B{$r}", $d->detail);
                $s->mergeCells("B{$r}:C{$r}");
                $s->setCellValue("D{$r}", $d->origine === 'direct' ? 'X' : '');
                $s->setCellValue("E{$r}", $d->origine === 'indirect' ? 'X' : '');
                $s->setCellValue("F{$r}", $d->est_sensible ? 'Oui' : '');
                $s->mergeCells("F{$r}:H{$r}");
                $s->getStyle("D{$r}:H{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                if ($d->est_sensible) {
                    $this->fond($s, "A{$r}:H{$r}", self::COULEUR_SENSIBLE);
                }
                $s->getStyle("A{$r}:H{$r}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                $premier = false;
                $r++;
            }
        }

        return $r;
    }

    private function formatListe(?array $valeurs): string
    {
        if (! is_array($valeurs) || empty($valeurs)) {
            return '';
        }
        return implode(', ', array_filter(array_map('trim', $valeurs)));
    }

    private function genererReference(): string
    {
        $annee = now()->year;
        $count = RegistreKyc::whereYear('created_at', $annee)->count() + 1;

        return \sprintf('REG-%d-%03d', $annee, $count);
    }
}
