<?php

/**
 * MatriceTemplate — Modele structure de la matrice de collecte
 * documentaire prealable (semaine 0). 5 poles, chaque pole liste des
 * elements a collecter (preuves) + des questions (poste, sous-traitants,
 * services internes) qui alimenteront l'organigramme.
 *
 * Source : 0. MATRICE DE COLLECTE DOCUMENTAIRE PREALABLE (SEMAINE 0).docx
 */

namespace App\Services\Methode2;

class MatriceTemplate
{
    /**
     * Renvoie la structure par defaut (sections + items + questions)
     * a injecter dans matrices_collecte.reponses lors de l'initialisation.
     *
     * Forme :
     * [
     *   {
     *     code: 'pole_1',
     *     pole: 'IT, Cyber & Securite',
     *     cibles: 'PMO / Direction Technique...',
     *     description: '...',
     *     items: [
     *       { code, libelle, attendu, reponse: '', piece_libelle?: '' }
     *     ],
     *     // Questions structurelles pour deduire l'organigramme
     *     organigramme: [
     *       { code, libelle, type: 'liste'|'texte', reponse: '' }
     *     ]
     *   }
     * ]
     */
    public static function defaut(): array
    {
        return [
            [
                'code' => 'pole_1',
                'pole' => 'Pole 1 — IT, Cyber & Securite',
                'cibles' => 'PMO / Direction Technique Centrale / Charge de securite et de logistique',
                'description' => 'Preuves des infrastructures et de la souverainete des donnees.',
                'items' => [
                    [
                        'code' => 'schema_archi',
                        'libelle' => 'Schema d\'architecture reseau',
                        'attendu' => 'Cartographie visuelle des flux et serveurs (Siege vs Hub Datacenters & interconnexions filiales).',
                        'reponse' => '',
                    ],
                    [
                        'code' => 'extractions_logiciels',
                        'libelle' => 'Extractions brutes (logiciels)',
                        'attendu' => 'Export Excel listant les logiciels, ERP et solutions SaaS utilises.',
                        'reponse' => '',
                    ],
                    [
                        'code' => 'pssi',
                        'libelle' => 'Politique de securite (PSSI)',
                        'attendu' => 'Charte informatique ou politique de securite opposable aux employes.',
                        'reponse' => '',
                    ],
                    [
                        'code' => 'directive_ia',
                        'libelle' => 'Directive IA (Shadow AI)',
                        'attendu' => 'Note interne autorisant ou encadrant l\'usage d\'IA publiques.',
                        'reponse' => '',
                    ],
                    [
                        'code' => 'videosurveillance',
                        'libelle' => 'Videosurveillance',
                        'attendu' => 'Capture d\'ecran ou doc certifiant la duree de retention.',
                        'reponse' => '',
                    ],
                ],
                'organigramme' => [
                    ['code' => 'services', 'libelle' => 'Services / equipes IT (separes par virgule)', 'type' => 'liste', 'reponse' => ''],
                    ['code' => 'postes', 'libelle' => 'Postes cles (DSI, RSSI, admin systeme...)', 'type' => 'liste', 'reponse' => ''],
                ],
            ],
            [
                'code' => 'pole_2',
                'pole' => 'Pole 2 — Capital Humain & Administration',
                'cibles' => 'Departement RH / Departement Administration, Comptabilite et Moyens Generaux',
                'description' => 'Preuves de traitement des donnees sensibles et de sante.',
                'items' => [
                    [
                        'code' => 'contrats_travail',
                        'libelle' => 'Contrats de travail',
                        'attendu' => 'Modele vierge employe local + modele expatrie.',
                        'reponse' => '',
                    ],
                    [
                        'code' => 'assurance_sante',
                        'libelle' => 'Assurance sante & avantages',
                        'attendu' => 'Modeles de contrats avec cliniques et assureurs partenaires.',
                        'reponse' => '',
                    ],
                    [
                        'code' => 'registre_visiteurs',
                        'libelle' => 'Registre des visiteurs',
                        'attendu' => 'Modele papier ou export logiciel du registre d\'accueil.',
                        'reponse' => '',
                    ],
                    [
                        'code' => 'biometrie',
                        'libelle' => 'Biometrie',
                        'attendu' => 'Nom et documentation de la solution biometrique (pointage, portes).',
                        'reponse' => '',
                    ],
                    [
                        'code' => 'flux_intra_groupe',
                        'libelle' => 'Couverture sante employes (flux intra-groupe)',
                        'attendu' => 'Identifier les entites du groupe qui recoivent les donnees medicales.',
                        'reponse' => '',
                    ],
                ],
                'organigramme' => [
                    ['code' => 'services', 'libelle' => 'Services RH/Administration (separes par virgule)', 'type' => 'liste', 'reponse' => ''],
                    ['code' => 'postes', 'libelle' => 'Postes cles (DRH, paie, administration...)', 'type' => 'liste', 'reponse' => ''],
                ],
            ],
            [
                'code' => 'pole_3',
                'pole' => 'Pole 3 — Metiers Assurance & Experience Client',
                'cibles' => 'Direction Marketing et Experience Client / Direction Developpement Commercial',
                'description' => 'Cartographie commerciale et reseau de distribution.',
                'items' => [
                    [
                        'code' => 'crm',
                        'libelle' => 'CRM & bases de prospection',
                        'attendu' => 'Export listant les logiciels marketing direct et gestion des leads.',
                        'reponse' => '',
                    ],
                    [
                        'code' => 'reseau_courtiers',
                        'libelle' => 'Reseau de courtiers',
                        'attendu' => 'Modeles de conventions standards avec courtiers/agents generaux.',
                        'reponse' => '',
                    ],
                ],
                'organigramme' => [
                    ['code' => 'services', 'libelle' => 'Services commerciaux/marketing (separes par virgule)', 'type' => 'liste', 'reponse' => ''],
                    ['code' => 'postes', 'libelle' => 'Postes cles (DC, marketing, animation reseau...)', 'type' => 'liste', 'reponse' => ''],
                ],
            ],
            [
                'code' => 'pole_4',
                'pole' => 'Pole 4 — Controle (Audit & Actuariat)',
                'cibles' => 'Direction Audit / Direction Actuariat Controle / Direction Risques, Controle Permanent et Conformite',
                'description' => 'Profilage, algorithmes, audits intra-groupe.',
                'items' => [
                    [
                        'code' => 'profilage_algos',
                        'libelle' => 'Outils de profilage & algorithmes (Art 25)',
                        'attendu' => 'Liste des logiciels/modeles actuariels utilises pour la tarification.',
                        'reponse' => '',
                    ],
                    [
                        'code' => 'rapports_audit',
                        'libelle' => 'Rapports d\'audit intra-groupe',
                        'attendu' => 'Modele vierge des rapports d\'audit des filiales.',
                        'reponse' => '',
                    ],
                ],
                'organigramme' => [
                    ['code' => 'services', 'libelle' => 'Services audit/risques/conformite (separes par virgule)', 'type' => 'liste', 'reponse' => ''],
                    ['code' => 'postes', 'libelle' => 'Postes cles (DRCC, audit interne, actuaire...)', 'type' => 'liste', 'reponse' => ''],
                ],
            ],
            [
                'code' => 'pole_5',
                'pole' => 'Pole 5 — Finance & Juridique',
                'cibles' => 'Direction Comptabilite Centrale / Direction Juridique',
                'description' => 'Reportings et flux financiers transfrontaliers.',
                'items' => [
                    [
                        'code' => 'rapports_holding',
                        'libelle' => 'Rapports maison-mere',
                        'attendu' => 'Liste des reportings nominatifs transferes a la holding ou aux filiales.',
                        'reponse' => '',
                    ],
                    [
                        'code' => 'flux_transfrontaliers',
                        'libelle' => 'Flux financiers transfrontaliers',
                        'attendu' => 'Plateformes de paiement / SaaS de paie/tresorerie hebergees hors CEDEAO. APIs/logiciels connectes a la BCEAO.',
                        'reponse' => '',
                    ],
                ],
                'organigramme' => [
                    ['code' => 'services', 'libelle' => 'Services finance/juridique (separes par virgule)', 'type' => 'liste', 'reponse' => ''],
                    ['code' => 'postes', 'libelle' => 'Postes cles (DAF, controle de gestion, juriste...)', 'type' => 'liste', 'reponse' => ''],
                ],
            ],
        ];
    }

    /**
     * Convertit les reponses (avec la cle 'organigramme' renseignee par
     * pole) en structure compatible avec organigrammes.structure.
     *
     * @param  array  $reponses  Issu de matrices_collecte.reponses
     * @return array  [{pole, services:[{nom, postes:[]}]}]
     */
    public static function deriverOrganigramme(array $reponses): array
    {
        $out = [];
        foreach ($reponses as $section) {
            $services = [];
            $servicesStr = '';
            $postesStr = '';
            foreach (($section['organigramme'] ?? []) as $champ) {
                if (($champ['code'] ?? null) === 'services') {
                    $servicesStr = (string) ($champ['reponse'] ?? '');
                } elseif (($champ['code'] ?? null) === 'postes') {
                    $postesStr = (string) ($champ['reponse'] ?? '');
                }
            }

            $listeServices = array_values(array_filter(array_map(
                fn ($s) => trim($s),
                explode(',', $servicesStr)
            )));
            $listePostes = array_values(array_filter(array_map(
                fn ($p) => trim($p),
                explode(',', $postesStr)
            )));

            // Si pas de service explicite mais des postes, on cree un service "generique"
            if (empty($listeServices) && ! empty($listePostes)) {
                $services[] = ['nom' => 'Equipe', 'postes' => $listePostes];
            } else {
                foreach ($listeServices as $s) {
                    $services[] = ['nom' => $s, 'postes' => $listePostes];
                }
            }

            // Ne garde que les poles dont on a au moins une info
            if (! empty($services) || ! empty($listePostes)) {
                $out[] = [
                    'pole' => $section['pole'] ?? ($section['code'] ?? 'Pole'),
                    'services' => $services,
                ];
            }
        }

        return $out;
    }
}
