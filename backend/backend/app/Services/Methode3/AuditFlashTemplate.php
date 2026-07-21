<?php

/**
 * AuditFlashTemplate — Modele fige du questionnaire "Audit Flash"
 * (Methode 3). 10 questions C-Level (Scan penal du dirigeant), chacune
 * notee Oui = 0 pt / Non = 10 pts / Je ne sais pas = 10 pts.
 *
 * Sources : AuditFlash/Matrice de l'audit Flash.docx et
 *           AuditFlash/Questionnaire_Audit_Flash.docx
 */

namespace App\Services\Methode3;

class AuditFlashTemplate
{
    public const REPONSES = ['Oui', 'Non', 'Je ne sais pas'];

    /**
     * Liste les 10 questions de l'Audit Flash sous la forme attendue par
     * QuestionnaireGenere.questions :
     * [{numero, texte, type:'liste', options, themes, domaine, enjeu, source_legale}, ...]
     *
     * @return array<int,array<string,mixed>>
     */
    public static function questions(): array
    {
        $items = [
            [
                'domaine' => 'KYC & CNI',
                'texte' => "Disposez-vous d'une autorisation préalable écrite de l'ARTCI pour collecter et stocker les pièces d'identité (CNI, passeports) de vos clients ?",
                'enjeu' => 'Collecte illégale de données sensibles. Sanction : 10 à 20 ans de prison (Art. 21).',
                'source_legale' => 'Loi 2013-450, Art. 7 & 21',
                'themes' => ['kyc', 'donnees_sensibles'],
            ],
            [
                'domaine' => 'Gouvernance',
                'texte' => "Pouvez-vous présenter immédiatement les récépissés de déclaration ARTCI pour vos fichiers clients et RH ?",
                'enjeu' => 'Fichiers non déclarés. Sanction : amende jusqu\'à 100 millions FCFA (Art. 5).',
                'source_legale' => 'Loi 2013-450, Art. 5',
                'themes' => ['gouvernance', 'declarations_artci'],
            ],
            [
                'domaine' => 'Responsabilité',
                'texte' => "Avez-vous officiellement désigné et déclaré un Correspondant à la Protection des Données (CPD) auprès de l'ARTCI ?",
                'enjeu' => 'Défaut de gouvernance. Circonstance aggravante en cas de contrôle.',
                'source_legale' => 'Arrêté 2024 MTND',
                'themes' => ['cpd', 'gouvernance'],
            ],
            [
                'domaine' => 'Souveraineté numérique',
                'texte' => "Disposez-vous d'une autorisation de l'ARTCI pour héberger vos emails et fichiers sur des serveurs étrangers (Google, Microsoft, iCloud, AWS) ?",
                'enjeu' => 'Transfert illicite hors CEDEAO. Violation de souveraineté des données.',
                'source_legale' => 'Loi 2013-450, Art. 26',
                'themes' => ['transferts', 'cloud'],
            ],
            [
                'domaine' => 'Shadow AI',
                'texte' => "Avez-vous une charte interne signée interdisant formellement à vos employés de soumettre des données clients à des IA gratuites (ChatGPT, Gemini, etc.) ?",
                'enjeu' => 'Fuite de secrets d\'affaires et violation de confidentialité.',
                'source_legale' => 'Loi 2013-450, Art. 39 & 41',
                'themes' => ['shadow_ai', 'charte_ia'],
            ],
            [
                'domaine' => 'Sous-traitance',
                'texte' => "Vos contrats avec vos prestataires (IT, comptable, gardiennage) contiennent-ils la clause de sécurité obligatoire imposée par la Loi ?",
                'enjeu' => 'Responsabilité par contagion : vous répondez pénalement des failles de vos prestataires.',
                'source_legale' => 'Loi 2013-450, Art. 20',
                'themes' => ['sous_traitance'],
            ],
            [
                'domaine' => 'Vidéosurveillance',
                'texte' => "Avez-vous la certitude — et la preuve légale — que vos caméras ne filment pas en continu les postes de travail de vos employés ?",
                'enjeu' => 'Atteinte à la vie privée. Nullité des preuves et plaintes prud\'homales.',
                'source_legale' => 'Décision 2025-1356',
                'themes' => ['video', 'vie_privee'],
            ],
            [
                'domaine' => 'Sécurité RH',
                'texte' => "Lorsqu'un employé quitte l'entreprise, existe-t-il une procédure technique garantissant la coupure de tous ses accès le jour même ?",
                'enjeu' => 'Sabotage interne ou vol de base de données par ex-employé.',
                'source_legale' => 'RGSSI 2025',
                'themes' => ['rh', 'acces'],
            ],
            [
                'domaine' => 'Sauvegarde & Ransomware',
                'texte' => "En cas d'attaque par un virus cette nuit, disposez-vous d'une sauvegarde totalement déconnectée de votre réseau pour redémarrer dès demain ?",
                'enjeu' => 'Chiffrement total des données et arrêt définitif de l\'activité.',
                'source_legale' => 'RGSSI 2025',
                'themes' => ['ransomware', 'sauvegarde'],
            ],
            [
                'domaine' => 'Gestion de crise',
                'texte' => "En cas de vol d'un ordinateur contenant des données clients, votre procédure est-elle prête pour notifier l'ARTCI dans un délai de 72 heures ?",
                'enjeu' => 'Dissimulation d\'incident. Amende maximale et fermeture administrative.',
                'source_legale' => 'Arrêté 2024 MTND',
                'themes' => ['crise', 'notification_72h'],
            ],
        ];

        $out = [];
        foreach ($items as $i => $q) {
            $out[] = [
                'numero' => $i + 1,
                'texte' => $q['texte'],
                'type' => 'liste',
                'options' => self::REPONSES,
                'domaine' => $q['domaine'],
                'enjeu' => $q['enjeu'],
                'source_legale' => $q['source_legale'],
                'themes' => $q['themes'],
            ];
        }

        return $out;
    }

    /**
     * Themes globaux du questionnaire (utile pour l'analyse d'ecarts).
     *
     * @return array<int,string>
     */
    public static function themes(): array
    {
        return ['audit_flash', 'scan_penal_dirigeant'];
    }

    /**
     * Description affichee au client en haut du questionnaire.
     */
    public static function description(): string
    {
        return "Audit Flash — Le Scan Pénal du Dirigeant. 10 questions pour évaluer l'exposition de votre entreprise aux risques RGPD / Loi 2013-450 / RGSSI. "
            ."Pour chaque question : Oui = 0 pt (mesure opérationnelle) ; Non ou Je ne sais pas = +10 pts. "
            ."Score 0-10 : conforme. 20-40 : zone de danger. 50-100 : zone rouge (infraction pénale continue).";
    }

    /**
     * Score d'une reponse pour le moteur de scoring "hemorragie".
     */
    public static function scoreReponse(?string $reponse): int
    {
        $r = trim(mb_strtolower((string) $reponse));
        if ($r === 'oui') return 0;
        if ($r === '' ) return 0;
        return 10;
    }
}
