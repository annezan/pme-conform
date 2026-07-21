<?php

/**
 * Controleur RefDataController — Donnees de reference (pays, secteurs).
 *
 * Sert les listes de reference (pays ISO, secteurs d'activite) utilisees
 * par les formulaires (creation client, etc.).
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Referentiel;
use App\Models\SecteurActivite;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\PathItem(
    path: "/api/ref-data/pays",
    get: new OA\Get(
        operationId: "ref-data-pays",
        summary: "Lister les pays",
        description: "Retourne la liste des pays ISO pour les formulaires",
        tags: ["Données de référence"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des pays",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                type: "object",
                                properties: [
                                    new OA\Property(property: "code", type: "string", example: "FR"),
                                    new OA\Property(property: "nom", type: "string", example: "France")
                                ]
                            ),
                            example: [
                                ["code" => "FR", "nom" => "France"],
                                ["code" => "BE", "nom" => "Belgique"],
                                ["code" => "CH", "nom" => "Suisse"]
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/ref-data/secteurs",
    get: new OA\Get(
        operationId: "ref-data-secteurs",
        summary: "Lister les secteurs d'activité",
        description: "Retourne la liste des secteurs d'activité actifs pour les formulaires",
        tags: ["Données de référence"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des secteurs d'activité",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                type: "object",
                                properties: [
                                    new OA\Property(property: "id", type: "integer", example: 1),
                                    new OA\Property(property: "nom", type: "string", example: "Technologie"),
                                    new OA\Property(property: "code", type: "string", example: "TECH")
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié")
        ]
    )
)]

#[OA\PathItem(
    path: "/api/ref-data/referentiels-types",
    get: new OA\Get(
        operationId: "ref-data-referentiels-types",
        summary: "Lister les types de référentiels",
        description: "Retourne la liste des types de référentiels disponibles",
        tags: ["Données de référence"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des types de référentiels",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                type: "object",
                                properties: [
                                    new OA\Property(property: "value", type: "string", example: "loi"),
                                    new OA\Property(property: "label", type: "string", example: "Loi")
                                ]
                            ),
                            example: [
                                ["value" => "loi", "label" => "Loi"],
                                ["value" => "decret", "label" => "Décret"],
                                ["value" => "arrete", "label" => "Arrêté"],
                                ["value" => "directive", "label" => "Directive"],
                                ["value" => "norme", "label" => "Norme"],
                                ["value" => "guide", "label" => "Guide"],
                                ["value" => "autre", "label" => "Autre"]
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié")
        ]
    )
)]

class RefDataController extends Controller
{
    public function pays(): JsonResponse
    {
        return response()->json(['data' => self::PAYS]);
    }

    /**
     * Secteurs d'activite : utilise la table normalisée secteurs_activite.
     * Retourne tous les secteurs actifs triés par nom.
     */
    public function secteurs(): JsonResponse
    {
        $secteurs = SecteurActivite::actif()
            ->orderBy('nom')
            ->pluck('nom')
            ->toArray();

        return response()->json(['data' => $secteurs]);
    }

    /**
     * Referentiels regroupes par secteur d'activite. Un referentiel peut
     * apparaitre sous plusieurs secteurs (s'il en couvre plusieurs).
     * Les referentiels sans secteur (= "tous secteurs") apparaissent dans
     * la categorie "Transversal".
     */
    public function referentielsParSecteur(): JsonResponse
    {
        $referentiels = Referentiel::query()
            ->where('statut', 'actif')
            ->with('secteursActivite')
            ->select('id', 'code', 'titre', 'autorite', 'type')
            ->orderBy('titre')
            ->get();

        $groupes = [];
        foreach ($referentiels as $ref) {
            $secteursNoms = $ref->secteursActivite->pluck('nom')->all();

            if (empty($secteursNoms)) {
                $groupes['Transversal'][] = $ref;
                continue;
            }

            foreach ($secteursNoms as $secteurNom) {
                $groupes[$secteurNom][] = $ref;
            }
        }

        ksort($groupes);

        $sortie = [];
        foreach ($groupes as $secteur => $refs) {
            $sortie[] = [
                'secteur' => $secteur,
                'referentiels' => $refs,
            ];
        }

        return response()->json(['data' => $sortie]);
    }

    public const SECTEURS_DEFAUT = [
        'Administration publique',
        'Agroalimentaire',
        'Assurance',
        'Banque & Finance',
        'BTP & Immobilier',
        'Commerce & Distribution',
        'Education & Formation',
        'Energie',
        'Hotellerie & Restauration',
        'Industrie',
        'Logistique & Transport',
        'Media & Communication',
        'Mines',
        'ONG & Associations',
        'Sante',
        'Services aux entreprises',
        'Telecom',
        'Tourisme',
    ];

    /**
     * Liste ISO 3166-1 (FR) des 250 pays/territoires reconnus.
     */
    private const PAYS = [
        'Afghanistan', 'Afrique du Sud', 'Albanie', 'Algerie', 'Allemagne', 'Andorre', 'Angola',
        'Anguilla', 'Antarctique', 'Antigua-et-Barbuda', 'Arabie saoudite', 'Argentine', 'Armenie',
        'Aruba', 'Australie', 'Autriche', 'Azerbaidjan', 'Bahamas', 'Bahrein', 'Bangladesh',
        'Barbade', 'Belarus', 'Belgique', 'Belize', 'Benin', 'Bermudes', 'Bhoutan', 'Bolivie',
        'Bonaire, Saint-Eustache et Saba', 'Bosnie-Herzegovine', 'Botswana', 'Bouvet (Ile)',
        'Bresil', 'Brunei Darussalam', 'Bulgarie', 'Burkina Faso', 'Burundi', 'Caimans (Iles)',
        'Cambodge', 'Cameroun', 'Canada', 'Cap-Vert', 'Centrafricaine (Republique)', 'Chili',
        'Chine', 'Christmas (Ile)', 'Chypre', 'Cocos (Iles)', 'Colombie', 'Comores',
        'Congo (Republique du)', 'Congo (Republique democratique du)', 'Cook (Iles)', 'Coree du Nord',
        'Coree du Sud', 'Costa Rica', 'Cote d\'Ivoire', 'Croatie', 'Cuba', 'Curacao', 'Danemark',
        'Djibouti', 'Dominicaine (Republique)', 'Dominique', 'Egypte', 'Emirats arabes unis',
        'Equateur', 'Erythree', 'Espagne', 'Estonie', 'Eswatini', 'Etats-Unis', 'Ethiopie',
        'Falkland (Iles)', 'Feroe (Iles)', 'Fidji', 'Finlande', 'France', 'Gabon', 'Gambie',
        'Georgie', 'Georgie du Sud-et-les Iles Sandwich du Sud', 'Ghana', 'Gibraltar', 'Grece',
        'Grenade', 'Groenland', 'Guadeloupe', 'Guam', 'Guatemala', 'Guernesey', 'Guinee',
        'Guinee equatoriale', 'Guinee-Bissau', 'Guyana', 'Guyane', 'Haiti', 'Heard-et-MacDonald (Iles)',
        'Honduras', 'Hong Kong', 'Hongrie', 'Iles mineures eloignees des Etats-Unis', 'Inde',
        'Indonesie', 'Irak', 'Iran', 'Irlande', 'Islande', 'Israel', 'Italie', 'Jamaique',
        'Japon', 'Jersey', 'Jordanie', 'Kazakhstan', 'Kenya', 'Kirghizistan', 'Kiribati', 'Kosovo',
        'Koweit', 'Laos', 'Lesotho', 'Lettonie', 'Liban', 'Liberia', 'Libye', 'Liechtenstein',
        'Lituanie', 'Luxembourg', 'Macao', 'Macedoine du Nord', 'Madagascar', 'Malaisie', 'Malawi',
        'Maldives', 'Mali', 'Malouines (Iles)', 'Malte', 'Mariannes du Nord (Iles)', 'Maroc',
        'Marshall (Iles)', 'Martinique', 'Maurice', 'Mauritanie', 'Mayotte', 'Mexique', 'Micronesie',
        'Moldavie', 'Monaco', 'Mongolie', 'Montenegro', 'Montserrat', 'Mozambique', 'Myanmar',
        'Namibie', 'Nauru', 'Nepal', 'Nicaragua', 'Niger', 'Nigeria', 'Niue', 'Norfolk (Ile)',
        'Norvege', 'Nouvelle-Caledonie', 'Nouvelle-Zelande', 'Oman', 'Ouganda', 'Ouzbekistan',
        'Pakistan', 'Palaos', 'Palestine', 'Panama', 'Papouasie-Nouvelle-Guinee', 'Paraguay',
        'Pays-Bas', 'Perou', 'Philippines', 'Pitcairn (Iles)', 'Pologne', 'Polynesie francaise',
        'Porto Rico', 'Portugal', 'Qatar', 'Reunion (La)', 'Roumanie', 'Royaume-Uni', 'Russie',
        'Rwanda', 'Sahara occidental', 'Saint-Barthelemy', 'Saint-Christophe-et-Nieves',
        'Saint-Kitts-et-Nevis', 'Saint-Marin', 'Saint-Martin (partie francaise)',
        'Saint-Martin (partie neerlandaise)', 'Saint-Pierre-et-Miquelon',
        'Saint-Vincent-et-les Grenadines', 'Sainte-Helene, Ascension et Tristan da Cunha',
        'Sainte-Lucie', 'Salomon (Iles)', 'Salvador', 'Samoa', 'Samoa americaines', 'Sao Tome-et-Principe',
        'Senegal', 'Serbie', 'Seychelles', 'Sierra Leone', 'Singapour', 'Slovaquie', 'Slovenie',
        'Somalie', 'Soudan', 'Soudan du Sud', 'Sri Lanka', 'Suede', 'Suisse', 'Suriname',
        'Svalbard et Jan Mayen', 'Syrie', 'Tadjikistan', 'Taiwan', 'Tanzanie', 'Tchad', 'Tchequie',
        'Terres australes francaises', 'Territoire britannique de l\'ocean Indien', 'Thailande',
        'Timor oriental', 'Togo', 'Tokelau', 'Tonga', 'Trinite-et-Tobago', 'Tunisie', 'Turkmenistan',
        'Turks-et-Caicos (Iles)', 'Turquie', 'Tuvalu', 'Ukraine', 'Uruguay', 'Vanuatu', 'Vatican',
        'Venezuela', 'Vierges americaines (Iles)', 'Vierges britanniques (Iles)', 'Vietnam',
        'Wallis-et-Futuna', 'Yemen', 'Zambie', 'Zimbabwe',
    ];
}
