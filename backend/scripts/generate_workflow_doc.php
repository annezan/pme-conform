<?php

/**
 * Genere le document Word "WORKFLOW_PME-CONFORM.docx" decrivant
 * l'ensemble du processus, de la creation de la mission jusqu'a
 * la generation de l'analyse d'ecarts.
 */

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;

$word = new PhpWord();
$word->setDefaultFontName('Calibri');
$word->setDefaultFontSize(11);

$word->addTableStyle('table-default', [
    'borderSize' => 6,
    'borderColor' => 'D1D5DB',
    'cellMargin' => 80,
]);

// Helpers de styles inline
$h1 = ['size' => 22, 'bold' => true, 'color' => '1E3A8A'];
$h2 = ['size' => 16, 'bold' => true, 'color' => '1E40AF'];
$h3 = ['size' => 13, 'bold' => true, 'color' => '2563EB'];
$bold = ['bold' => true];
$italic = ['italic' => true, 'color' => '6B7280'];
$code = ['name' => 'Consolas', 'size' => 9, 'color' => '374151'];

$pH = ['spaceBefore' => 240, 'spaceAfter' => 120];
$pH2 = ['spaceBefore' => 200, 'spaceAfter' => 100];
$pH3 = ['spaceBefore' => 160, 'spaceAfter' => 80];
$pNorm = ['spaceAfter' => 100];
$pCenter = ['alignment' => Jc::CENTER];

$cellHeader = ['bgColor' => '1E40AF', 'valign' => 'center'];
$fontHeader = ['bold' => true, 'color' => 'FFFFFF', 'size' => 10];
$cellBody = ['valign' => 'top'];

$section = $word->addSection([
    'marginTop' => Converter::cmToTwip(2),
    'marginBottom' => Converter::cmToTwip(2),
    'marginLeft' => Converter::cmToTwip(2),
    'marginRight' => Converter::cmToTwip(2),
]);

// Petite fabrique de puces (sans addListItem)
$puce = function ($section, string $texte, int $niveau = 0) use ($pNorm) {
    $indent = $niveau * 360;
    $para = ['spaceAfter' => 60, 'indentation' => ['left' => $indent + 200]];
    $p = $section->addTextRun($para);
    $p->addText('• ', ['bold' => true, 'color' => '2563EB']);
    $p->addText($texte);
};

// ===========================================================
// COUVERTURE
// ===========================================================
$section->addText('PME-CONFORM', ['size' => 28, 'bold' => true, 'color' => '1E3A8A'], $pCenter);
$section->addText('Plateforme de conformite reglementaire AS Consulting', ['size' => 14, 'color' => '4B5563'], $pCenter);
$section->addTextBreak(2);
$section->addText('GUIDE DU WORKFLOW', ['size' => 22, 'bold' => true, 'color' => '111827'], $pCenter);
$section->addText('De la creation de la mission a l\'analyse d\'ecarts', ['size' => 14, 'italic' => true, 'color' => '4B5563'], $pCenter);
$section->addTextBreak(3);
$section->addText('Date de redaction : ' . date('d/m/Y'), ['size' => 11], $pCenter);
$section->addText('Version : 1.0', ['size' => 11], $pCenter);
$section->addText('Auteur : AS Consulting', ['size' => 11], $pCenter);

$section->addPageBreak();

// ===========================================================
// 1. INTRODUCTION
// ===========================================================
$section->addText('1. Introduction', $h1, $pH);

$section->addText(
    'PME-CONFORM est la plateforme de conformite reglementaire developpee par AS Consulting. '
    . 'Elle accompagne les entreprises (clients) dans la realisation d\'audits de conformite (RGPD, ARTCI, '
    . 'CIMA, RGSSI, etc.) en automatisant la collecte d\'informations, la generation des questionnaires '
    . 'd\'audit, et la production d\'un rapport d\'ecarts entre la situation reelle du client et les exigences '
    . 'des referentiels reglementaires.',
    null,
    $pNorm
);

$section->addText(
    'Le present document decrit le workflow complet, de la creation d\'une mission par AS Consulting '
    . 'jusqu\'au lancement de l\'analyse d\'ecarts. Il distingue deux methodes de travail (Methode 1 et '
    . 'Methode 2) et detaille les actions cote AS Consulting (agent) comme cote client.',
    null,
    $pNorm
);

// ===========================================================
// 2. ACTEURS & ROLES
// ===========================================================
$section->addText('2. Acteurs et roles', $h1, $pH);

$table = $section->addTable('table-default');
$table->addRow();
$table->addCell(3500, $cellHeader)->addText('Role', $fontHeader);
$table->addCell(7500, $cellHeader)->addText('Responsabilites', $fontHeader);

$lignes = [
    ['Admin', 'Gestion globale (utilisateurs, modules, agents IA, audit). Acces a toutes les missions.'],
    ['Manager', 'Supervise un portefeuille de missions ASC. Acces lecture/ecriture sur toutes les missions.'],
    ['Consultant', 'Cree et pilote les missions sur ses clients : matrice, organigramme, questionnaires, analyses.'],
    ['Client / client_admin', 'Renseigne la matrice de collecte, uploade ses documents, remplit les formulaires de sa mission. Consulte les analyses produites.'],
];
foreach ($lignes as $l) {
    $table->addRow();
    $table->addCell(3500, $cellBody)->addText($l[0], $bold);
    $table->addCell(7500, $cellBody)->addText($l[1]);
}

$section->addTextBreak(1);

// ===========================================================
// 3. CYCLE DE VIE D'UNE MISSION
// ===========================================================
$section->addText('3. Cycle de vie d\'une mission', $h1, $pH);

$section->addText(
    'Une mission represente un dossier de conformite ouvert pour un client. Elle est l\'unite '
    . 'centrale autour de laquelle s\'articulent les documents, les formulaires, l\'organigramme et '
    . 'les analyses d\'ecarts. Chaque mission a une methode de travail, choisie a la creation :',
    null,
    $pNorm
);

$puce($section, 'Methode 1 - Classique : AS Consulting concoit un formulaire ad hoc, le client le remplit (avec ou sans aide d\'un agent en interview).');
$puce($section, 'Methode 2 - IA dynamique : matrice de collecte semaine 0 -> organigramme deduit -> questionnaires generes automatiquement par l\'IA pour chaque pole/service.');

$section->addTextBreak(1);

// ----- 3.1
$section->addText('3.1 Etape 1 - Creation de la mission (AS Consulting)', $h2, $pH2);
$section->addText('Acteur : consultant / manager / admin', $italic, $pNorm);
$p = $section->addTextRun($pNorm);
$p->addText('Ecran : ', $bold);
$p->addText('http://localhost:5173/missions -> Nouvelle mission', $code);

$section->addText('Champs saisis :', $bold, $pNorm);
$puce($section, 'Client (selection dans la liste des clients).');
$puce($section, 'Titre de la mission.');
$puce($section, 'Priorite (basse, normale, haute, urgente).');
$puce($section, 'Methode de travail : Methode 1 (Classique) ou Methode 2 (IA dynamique).');

$section->addText(
    'Le champ "Type de mission" a ete retire du formulaire (toujours fixe a audit_conformite cote backend). '
    . 'Une reference unique est generee automatiquement (format MISS-AAAA-NNN).',
    null,
    $pNorm
);

$section->addText('Effets cote backend :', $bold, $pNorm);
$puce($section, 'La mission est creee en statut brouillon avec le responsable = utilisateur connecte.');
$puce($section, 'Si Methode 1 : un formulaire (questionnaire_genere) vierge est cree automatiquement, attache a la mission. Le consultant pourra y ajouter les questions ; le client pourra y repondre.');
$puce($section, 'Si Methode 2 : une matrice de collecte (matrices_collecte) est creee automatiquement et pre-remplie avec le template structure des 5 poles.');

$section->addTextBreak(1);

// ----- 3.2
$section->addText('3.2 Methode 2 - Matrice de collecte initiale (cote client)', $h2, $pH2);
$section->addText('Acteur : client (assiste eventuellement par un agent ASC en interview)', $italic, $pNorm);
$p = $section->addTextRun($pNorm);
$p->addText('Ecran : ', $bold);
$p->addText('http://localhost:5173/mes-matrices puis /mes-matrices/{missionId}', $code);

$section->addText(
    'La matrice est structuree en 5 poles (issus de la matrice de collecte documentaire prealable - '
    . 'semaine 0). Chaque pole contient une liste d\'items (preuves attendues) et des champs structurels '
    . '(services / postes cles) qui serviront a deduire l\'organigramme.',
    null,
    $pNorm
);

$polesTable = $section->addTable('table-default');
$polesTable->addRow();
$polesTable->addCell(2200, $cellHeader)->addText('Pole', $fontHeader);
$polesTable->addCell(3500, $cellHeader)->addText('Cibles internes', $fontHeader);
$polesTable->addCell(5300, $cellHeader)->addText('Exemples d\'items collectes', $fontHeader);

$poles = [
    ['Pole 1 - IT, Cyber et Securite', 'PMO, Direction Technique, RSSI', 'Schema reseau, PSSI, directive Shadow AI, retention videosurveillance, extractions logiciels.'],
    ['Pole 2 - Capital Humain et Administration', 'RH, Administration, Comptabilite', 'Modeles contrats locaux/expatries, assurance sante, registre des visiteurs, biometrie, flux intra-groupe.'],
    ['Pole 3 - Metiers Assurance et Experience Client', 'Marketing et Experience Client, Developpement Commercial', 'CRM et bases de prospection, conventions courtiers/agents generaux.'],
    ['Pole 4 - Controle (Audit et Actuariat)', 'Audit, Actuariat Controle, Risques et Conformite', 'Outils de profilage (Art 25), modeles de rapports d\'audit intra-groupe.'],
    ['Pole 5 - Finance et Juridique', 'Comptabilite Centrale, Juridique', 'Reportings nominatifs maison-mere, flux financiers transfrontaliers, plateformes BCEAO.'],
];
foreach ($poles as $pp) {
    $polesTable->addRow();
    $polesTable->addCell(2200, $cellBody)->addText($pp[0], $bold);
    $polesTable->addCell(3500, $cellBody)->addText($pp[1]);
    $polesTable->addCell(5300, $cellBody)->addText($pp[2]);
}

$section->addTextBreak(1);

$section->addText('Actions disponibles pour le client :', $bold, $pNorm);
$puce($section, 'Repondre item par item (texte libre, lien interne ou la mention "Inexistant").');
$puce($section, 'Renseigner par pole les services et postes cles (ces champs alimentent la generation de l\'organigramme).');
$puce($section, 'Uploader les pieces de conviction (PSSI, schema reseau, contrats types, etc.).');
$puce($section, 'Sauvegarder la progression (statut en_cours).');
$puce($section, 'Cliquer sur "Generer organigramme" pour deduire automatiquement la structure depuis ses reponses.');
$puce($section, 'Remettre la matrice a AS Consulting (statut remise) lorsqu\'elle est complete.');

$section->addTextBreak(1);

// ----- 3.3
$section->addText('3.3 Methode 2 - Organigramme', $h2, $pH2);
$section->addText('Acteurs : client (proposition automatique) + consultant ASC (validation, figeage)', $italic, $pNorm);
$p = $section->addTextRun($pNorm);
$p->addText('Ecran : ', $bold);
$p->addText('http://localhost:5173/missions/{id}/organigramme', $code);

$section->addText(
    'L\'organigramme stocke la structure organisationnelle (poles -> services -> postes) de l\'entreprise. '
    . 'Deux modes :',
    null,
    $pNorm
);
$puce($section, 'Saisie : structure JSON arborescente, ajustable par l\'agent (ajouter/retirer pole, service, postes).');
$puce($section, 'Upload : un fichier image/PDF/DOCX/XLSX represente l\'organigramme.');

$section->addText(
    'Lorsque la matrice du client est validee, le bouton "Generer organigramme" cree automatiquement la '
    . 'structure depuis les champs services / postes des 5 poles. L\'agent peut ensuite affiner. '
    . 'Le bouton "Figer et generer" :',
    null,
    $pNorm
);
$puce($section, 'Marque l\'organigramme comme fige (statut = fige).');
$puce($section, 'Declenche immediatement la generation des questionnaires d\'audit par l\'IA (un questionnaire par pole/service detecte).');

$section->addTextBreak(1);

// ----- 3.4
$section->addText('3.4 Formulaires / questionnaires (Methode 1 et 2)', $h2, $pH2);
$section->addText('Acteurs : client et agent AS Consulting', $italic, $pNorm);
$p = $section->addTextRun($pNorm);
$p->addText('Ecrans : ', $bold);
$p->addText('Cote client : /mes-formulaires. Cote ASC : /missions/{id} (section Formulaires) puis /questionnaires-generes/{qid}.', $code);

$section->addText('Methode 1 :', $bold, $pNorm);
$puce($section, 'Un formulaire vierge a ete cree automatiquement a la creation de la mission.');
$puce($section, 'L\'agent ASC ajoute les questions adaptees au client (PUT /questionnaires-generes/{q}).');
$puce($section, 'Le client repond ou l\'agent saisit les reponses lors d\'un interview (Google Meet ou autre).');

$section->addText('Methode 2 :', $bold, $pNorm);
$puce($section, 'Apres figeage de l\'organigramme, l\'IA produit un questionnaire par pole/service detecte.');
$puce($section, 'Themes auto : biometrie, video, cartographie, sous-traitance, etc., selon la nature de l\'entite.');

$section->addText('Actions sur un formulaire :', $bold, $pNorm);
$puce($section, 'Consulter (lecture seule pour suivre la couverture).');
$puce($section, 'Editer les questions (consultant ASC).');
$puce($section, 'Saisir les reponses (client ou agent en interview, en mode brouillon ou finalise).');
$puce($section, 'Supprimer le formulaire (consultant ASC ou client avec acces).');

$section->addTextBreak(1);

// ----- 3.5
$section->addText('3.5 Documents du client', $h2, $pH2);
$p = $section->addTextRun($pNorm);
$p->addText('Ecran : ', $bold);
$p->addText('http://localhost:5173/mes-documents (cote client) + /documents/upload (cote ASC).', $code);

$section->addText(
    'Le client uploade ses documents (PDF, DOCX) dans son espace. Chaque fichier est analyse en arriere-plan : '
    . 'extraction du texte, detection eventuelle de questionnaire deja rempli, decoupe en chunks et '
    . 'indexation vectorielle (pgvector) si disponible. Les documents sont rattaches a une mission '
    . '("Boite de reception" par defaut, ou la mission active selon le contexte).',
    null,
    $pNorm
);

$section->addText('Statuts d\'un document :', $bold, $pNorm);
$puce($section, 'en_attente : upload recu, en file de traitement.');
$puce($section, 'en_traitement : extraction et indexation en cours.');
$puce($section, 'indexe : pret pour les analyses.');
$puce($section, 'erreur : extraction ou indexation a echoue.');

$section->addTextBreak(1);

// ===========================================================
// 4. ANALYSE D'ECARTS
// ===========================================================
$section->addText('4. Lancement de l\'analyse d\'ecarts', $h1, $pH);
$section->addText('Acteur : consultant / manager / admin (le client a uniquement la consultation).', $italic, $pNorm);
$p = $section->addTextRun($pNorm);
$p->addText('Ecran : ', $bold);
$p->addText('http://localhost:5173/analyses/nouvelle (stepper 4 etapes).', $code);

$section->addText('4.1 Etape 1 - Selection de la mission', $h2, $pH2);
$section->addText(
    'L\'utilisateur selectionne une mission deja existante dans la liste. Les champs "Titre" et "Type" '
    . 'ont ete retires du stepper : la mission est l\'objet metier qui porte ces informations. Le mode '
    . '(Methode 1 ou Methode 2) est affiche en badge dans la fiche de selection.',
    null,
    $pNorm
);

$section->addText('4.2 Etape 2 - Sources d\'analyse', $h2, $pH2);
$section->addText('L\'analyse exploite deux types de sources :', null, $pNorm);
$puce($section, 'Documents uploades par le client (PDF, DOCX) - les pieces deja indexees sont pre-cochees ; on peut en uploader de nouveaux dans la zone drag-drop.');
$puce($section, 'Formulaires renseignes lies a la mission (Methode 1 ou Methode 2) - ceux comportant au moins une reponse sont pre-coches.');

$section->addText(
    'Le moteur convertit chaque formulaire selectionne en Document virtuel (type questionnaire_synthese), '
    . 'avec un chunk par paire question/reponse, ce qui permet de retrouver les preuves textuelles avec la '
    . 'meme precision que pour les documents PDF.',
    null,
    $pNorm
);

$section->addText('4.3 Etape 3 - Referentiels', $h2, $pH2);
$section->addText(
    'Les referentiels reglementaires sont pre-selectionnes selon le secteur d\'activite du client. '
    . 'L\'utilisateur peut ajouter ou retirer des corpus (ex : RGPD, ARTCI biometrie, RGSSI, CIMA, etc.). '
    . 'Au moins un referentiel doit etre selectionne pour pouvoir lancer l\'analyse.',
    null,
    $pNorm
);

$section->addText('4.4 Etape 4 - Recapitulatif et lancement', $h2, $pH2);
$section->addText('Choix du mode d\'analyse :', $bold, $pNorm);
$puce($section, 'Rapide (~ 30 s) : detection des ecarts par recherche vectorielle (pgvector) ou plein texte. Redaction automatique sans LLM. Ideal pour un premier diagnostic.');
$puce($section, 'Enrichi - IA (~ 20+ min) : le LLM redige titre, description et recommandation pour chaque ecart. Qualitatif mais lent.');

$section->addText(
    'Au lancement, l\'analyse est creee en statut en_attente puis le job AnalyserMissionJob est dispatche. '
    . 'L\'utilisateur est redirige vers la page de detail qui affiche la progression en temps reel.',
    null,
    $pNorm
);

$section->addTextBreak(1);

// ===========================================================
// 5. PIPELINE
// ===========================================================
$section->addText('5. Pipeline du moteur d\'analyse (GapAnalysisService)', $h1, $pH);
$section->addText('Le service execute la sequence suivante :', null, $pNorm);
$puce($section, 'Materialisation : pour chaque questionnaire selectionne, creation/mise a jour d\'un Document miroir + DocumentChunks (1 par paire Q/R).');
$puce($section, 'Chargement des exigences : tous les chunks des referentiels selectionnes (table referentiel_chunks).');
$puce($section, 'Pour chaque exigence : recherche de la meilleure preuve dans les chunks documents/formulaires (RAG pgvector ou plein texte).');
$puce($section, 'Verdict : conforme (score eleve), preuve insuffisante (score moyen) ou absence totale (score faible).');
$puce($section, 'Creation des ecarts non conformes avec leur gravite (critique / majeur / mineur), extrait de preuve et metadonnees.');
$puce($section, 'Calcul du score de conformite global et generation de la synthese.');
$puce($section, 'Generation du rapport PowerPoint (RapportPptxGenerator) telechargeable depuis la page de detail.');
$puce($section, 'Optionnel : enrichissement IA des ecarts via le job EnrichirEcartsJob.');

$section->addTextBreak(1);

// ===========================================================
// 6. SCHEMA RECAPITULATIF
// ===========================================================
$section->addText('6. Schema recapitulatif du workflow', $h1, $pH);

$flowTable = $section->addTable('table-default');
$flowTable->addRow();
$flowTable->addCell(1500, $cellHeader)->addText('Etape', $fontHeader);
$flowTable->addCell(2500, $cellHeader)->addText('Acteur', $fontHeader);
$flowTable->addCell(3500, $cellHeader)->addText('Ecran', $fontHeader);
$flowTable->addCell(3500, $cellHeader)->addText('Resultat', $fontHeader);

$flow = [
    ['1', 'Consultant ASC', '/missions (Nouvelle mission)', 'Mission creee + matrice (M2) ou formulaire vierge (M1) auto-cree.'],
    ['2', 'Client', '/mes-matrices/{id}', 'Matrice 5 poles renseignee + pieces uploadees.'],
    ['3', 'Client', 'Bouton "Generer organigramme"', 'Structure organigramme deduite des reponses services/postes.'],
    ['4', 'Consultant ASC', '/missions/{id}/organigramme', 'Organigramme ajuste puis fige -> generation IA des questionnaires.'],
    ['5a', 'Client', '/mes-formulaires', 'Reponses aux questionnaires generes (M2) ou au formulaire ASC (M1).'],
    ['5b', 'Agent ASC', '/missions/{id} (section Formulaires)', 'Saisie des reponses pendant un interview (Meet/Zoom).'],
    ['6', 'Client', '/mes-documents', 'Upload des documents (PDF/DOCX), indexation pgvector automatique.'],
    ['7', 'Consultant ASC', '/analyses/nouvelle', 'Selection mission + sources (docs + formulaires) + referentiels.'],
    ['8', 'Moteur', 'AnalyserMissionJob (queue)', 'Analyse RAG -> ecarts horodates + score + rapport PPTX.'],
    ['9', 'Client / ASC', '/analyses/{id}', 'Consultation des ecarts + telechargement du rapport.'],
];
foreach ($flow as $row) {
    $flowTable->addRow();
    $flowTable->addCell(1500, $cellBody)->addText($row[0], $bold);
    $flowTable->addCell(2500, $cellBody)->addText($row[1]);
    $flowTable->addCell(3500, $cellBody)->addText($row[2], $code);
    $flowTable->addCell(3500, $cellBody)->addText($row[3]);
}

$section->addTextBreak(1);

// ===========================================================
// 7. POINTS DE VIGILANCE
// ===========================================================
$section->addText('7. Points de vigilance et bonnes pratiques', $h1, $pH);
$puce($section, 'Une mission Methode 2 sans matrice renseignee ne peut pas generer d\'organigramme exploitable. La matrice doit comporter au moins un pole avec services ou postes renseignes.');
$puce($section, 'Tant qu\'un document est en statut en_traitement, il n\'est pas selectionnable comme source d\'analyse. Attendre le statut indexe.');
$puce($section, 'La selection d\'au moins un document ou un formulaire est necessaire pour lancer une analyse (l\'API rejette une analyse sans source).');
$puce($section, 'Le client n\'a acces qu\'aux missions/matrices/documents/formulaires des entreprises auxquelles son compte est rattache. Le controle est applique au niveau de chaque controleur.');
$puce($section, 'L\'enrichissement IA est lent (plus de 20 min). Le mode rapide est recommande pour un premier diagnostic ; l\'enrichissement peut etre relance ulterieurement depuis la page de detail.');
$puce($section, 'Les pieces uploadees dans la matrice sont distinctes des documents indexes : elles servent de preuves de conviction mais ne sont pas integrees a l\'analyse RAG (sauf si elles sont aussi uploadees via /mes-documents).');

$section->addTextBreak(1);

// ===========================================================
// 8. ANNEXE TECHNIQUE
// ===========================================================
$section->addText('8. Annexe technique (mapping API/UI)', $h1, $pH);

$apiTable = $section->addTable('table-default');
$apiTable->addRow();
$apiTable->addCell(2500, $cellHeader)->addText('Domaine', $fontHeader);
$apiTable->addCell(4500, $cellHeader)->addText('Endpoint', $fontHeader);
$apiTable->addCell(4000, $cellHeader)->addText('Usage', $fontHeader);

$endpoints = [
    ['Missions', 'POST /api/missions', 'Cree une mission. Cree auto formulaire (M1) ou matrice (M2).'],
    ['Matrices', 'GET /api/client/matrices', 'Liste les matrices accessibles au client connecte.'],
    ['Matrices', 'GET /api/missions/{m}/matrice', 'Detail (pre-rempli avec template si vide).'],
    ['Matrices', 'PUT /api/missions/{m}/matrice', 'Sauvegarde des reponses structurees.'],
    ['Matrices', 'POST /api/missions/{m}/matrice/deriver-organigramme', 'Deduit la structure organigramme depuis la matrice.'],
    ['Matrices', 'POST /api/missions/{m}/matrice/pieces', 'Upload d\'une piece de conviction.'],
    ['Organigramme', 'GET/PUT /api/missions/{m}/organigramme', 'Lecture/edition de la structure.'],
    ['Organigramme', 'POST /api/missions/{m}/organigramme/figer', 'Fige + declenche generation IA des questionnaires.'],
    ['Formulaires', 'GET /api/client/questionnaires', 'Liste les formulaires accessibles au client connecte.'],
    ['Formulaires', 'GET/PUT /api/questionnaires-generes/{q}', 'Lecture/edition d\'un formulaire.'],
    ['Formulaires', 'PUT /api/questionnaires-generes/{q}/reponses', 'Saisie/maj des reponses.'],
    ['Formulaires', 'DELETE /api/questionnaires-generes/{q}', 'Suppression.'],
    ['Documents', 'POST /api/client/documents', 'Upload d\'un document client.'],
    ['Analyses', 'POST /api/analyses', 'Lance une analyse (mission_id, referentiels_ids, documents_ids, questionnaires_ids).'],
    ['Analyses', 'GET /api/analyses/{a}', 'Detail + ecarts + rapport.'],
    ['Analyses', 'GET /api/analyses/{a}/rapport', 'Telechargement PPTX.'],
];
foreach ($endpoints as $e) {
    $apiTable->addRow();
    $apiTable->addCell(2500, $cellBody)->addText($e[0], $bold);
    $apiTable->addCell(4500, $cellBody)->addText($e[1], $code);
    $apiTable->addCell(4000, $cellBody)->addText($e[2]);
}

$section->addTextBreak(2);
$section->addText('Fin du document.', $italic, $pCenter);

// ===========================================================
// SAUVEGARDE
// ===========================================================
$cible = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'WORKFLOW_PME-CONFORM.docx';
$writer = \PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007');
$writer->save($cible);

echo "Document genere : {$cible}\n";
echo 'Taille : ' . number_format(filesize($cible)) . " octets\n";
