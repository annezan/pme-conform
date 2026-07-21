<?php

/**
 * Seeder pour les secteurs d'activité par défaut.
 *
 * Initialise la table secteurs_activite avec les secteurs
 * prédéfinis dans RefDataController::SECTEURS_DEFAUT si la table est vide.
 */

namespace Database\Seeders;

use App\Http\Controllers\Api\RefDataController;
use App\Models\SecteurActivite;
use Illuminate\Database\Seeder;

class SecteursActiviteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Vérifier si la table est déjà remplie
        if (SecteurActivite::count() > 0) {
            $this->command->info('La table secteurs_activite contient déjà des données. Le seeder a été ignoré.');
            return;
        }

        // Utiliser la constante SECTEURS_DEFAUT du RefDataController
        $secteursParDefaut = RefDataController::SECTEURS_DEFAUT;

        $secteurs = [];
        foreach ($secteursParDefaut as $index => $nomSecteur) {
            $secteurs[] = [
                'nom' => $nomSecteur,
                'code' => $this->genererCode($nomSecteur),
                'description' => $this->genererDescription($nomSecteur),
                'is_actif' => true,
            ];
        }

        foreach ($secteurs as $secteur) {
            SecteurActivite::create($secteur);
        }

        $this->command->info(count($secteurs) . ' secteurs d\'activité ont été créés avec succès depuis SECTEURS_DEFAUT.');
    }

    /**
     * Génère un code à partir du nom du secteur.
     */
    private function genererCode(string $nom): string
    {
        // Remplacer les caractères spéciaux par des underscores et convertir en majuscules
        $code = strtoupper($nom);
        $code = str_replace(['&', ' ', 'é', 'è', 'ê', 'à', 'ù', 'ç', 'ï', 'î'], ['_', '_', 'E', 'E', 'E', 'A', 'U', 'C', 'I', 'I'], $code);
        
        // Supprimer tous les caractères non alphanumériques sauf les underscores
        $code = preg_replace('/[^A-Z0-9_]/', '_', $code);
        
        // Remplacer les underscores multiples par un seul
        $code = preg_replace('/_+/', '_', $code);
        
        // Supprimer les underscores au début et à la fin
        $code = trim($code, '_');
        
        // Limiter à 15 caractères maximum pour plus de flexibilité
        return substr($code, 0, 15);
    }

    /**
     * Génère une description à partir du nom du secteur.
     */
    private function genererDescription(string $nom): string
    {
        $descriptions = [
            'Administration publique' => 'Services gouvernementaux, administrations publiques, collectivités territoriales',
            'Agroalimentaire' => 'Production alimentaire, transformation agricole, industries agroalimentaires',
            'Assurance' => 'Compagnies d\'assurance, courtiers, intermédiaires d\'assurance',
            'Banque & Finance' => 'Banques, établissements financiers, services financiers, fintech',
            'BTP & Immobilier' => 'Bâtiment et travaux publics, promotion immobilière, construction',
            'Commerce & Distribution' => 'Commerce de détail, commerce de gros, distribution, e-commerce',
            'Education & Formation' => 'Etablissements d\'enseignement, centres de formation, éducation en ligne',
            'Energie' => 'Production et distribution d\'énergie, renouvelable, pétrole et gaz',
            'Hotellerie & Restauration' => 'Hôtels, restaurants, cafés, services de restauration',
            'Industrie' => 'Industrie manufacturière, production, usines, transformation',
            'Logistique & Transport' => 'Transport de marchandises et de passagers, logistique, entreposage',
            'Media & Communication' => 'Presse, radio, télévision, agences de communication, publicité',
            'Mines' => 'Exploitation minière, extraction de ressources naturelles',
            'ONG & Associations' => 'Organisations non gouvernementales, associations à but non lucratif',
            'Sante' => 'Etablissements de santé, cabinets médicaux, pharmaceutiques',
            'Services aux entreprises' => 'Conseil, audit, services aux entreprises, support administratif',
            'Telecom' => 'Opérateurs télécoms, fournisseurs d\'accès internet, services de communication',
            'Tourisme' => 'Agences de voyages, tourisme, loisirs, activités touristiques',
        ];

        return $descriptions[$nom] ?? "Secteur d'activité : {$nom}";
    }
}
