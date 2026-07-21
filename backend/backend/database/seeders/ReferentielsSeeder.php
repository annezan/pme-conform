<?php

/**
 * Seeder ReferentielsSeeder — Base de référentiels ARTCI de départ.
 *
 * Crée les référentiels de base sans le champ secteurs_activite.
 * Les relations avec les secteurs d'activité seront gérées via l'interface admin.
 */

namespace Database\Seeders;

use App\Models\Referentiel;
use Illuminate\Database\Seeder;

class ReferentielsSeeder extends Seeder
{
    public function run(): void
    {
        $referentiels = [
            [
                'code' => 'ARTCI-LOI-2013-450',
                'titre' => 'Loi n° 2013-450 relative à la protection des données à caractère personnel',
                'description' => 'Loi ivoirienne fondatrice sur la protection des données personnelles.',
                'autorite' => 'ARTCI',
                'version' => '2013',
                'date_publication' => '2013-06-19',
                'date_entree_vigueur' => '2013-06-19',
                'type' => 'loi',
                'statut' => 'actif',
            ],
            [
                'code' => 'ARTCI-DECRET-2015-79',
                'titre' => 'Décret n° 2015-79 portant organisation et fonctionnement de l\'ARTCI',
                'description' => 'Décret d\'application fixant les modalités de contrôle et les pouvoirs de sanction.',
                'autorite' => 'ARTCI',
                'version' => '2015',
                'date_publication' => '2015-02-11',
                'date_entree_vigueur' => '2015-02-11',
                'type' => 'decret',
                'statut' => 'actif',
            ],
            [
                'code' => 'ARTCI-LOI-2013-546',
                'titre' => 'Loi n° 2013-546 relative aux transactions électroniques',
                'description' => 'Régime juridique des transactions électroniques et signature électronique.',
                'autorite' => 'ARTCI',
                'version' => '2013',
                'date_publication' => '2013-07-30',
                'date_entree_vigueur' => '2013-07-30',
                'type' => 'loi',
                'statut' => 'actif',
            ],
            [
                'code' => 'ISO-27001-2022',
                'titre' => 'ISO/IEC 27001:2022 — Système de management de la sécurité de l\'information',
                'description' => 'Norme internationale pour le SMSI.',
                'autorite' => 'ISO',
                'version' => '2022',
                'date_publication' => '2022-10-25',
                'type' => 'norme',
                'statut' => 'actif',
            ],
            [
                'code' => 'ISO-27701-2019',
                'titre' => 'ISO/IEC 27701:2019 — Extension pour la gestion de la vie privée',
                'description' => 'Extension ISO 27001 pour la protection des données personnelles.',
                'autorite' => 'ISO',
                'version' => '2019',
                'date_publication' => '2019-08-01',
                'type' => 'norme',
                'statut' => 'actif',
            ],
            [
                'code' => 'UEMOA-DIR-2006',
                'titre' => 'Directive UEMOA n° 01/2006/CM/UEMOA portant harmonisation de la protection des données',
                'description' => 'Cadre harmonisé ouest-africain pour la protection des données.',
                'autorite' => 'UEMOA',
                'version' => '2006',
                'date_publication' => '2006-09-15',
                'type' => 'directive',
                'statut' => 'actif',
            ],
        ];

        foreach ($referentiels as $data) {
            Referentiel::updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }
    }
}
