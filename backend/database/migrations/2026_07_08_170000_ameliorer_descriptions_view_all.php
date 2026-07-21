<?php

/**
 * Réécrit les descriptions des permissions view-all-* en français correct
 * et sans jargon technique, pour l'écran /admin/permissions.
 *
 * Avant : "Voir/gérer tous les analyses (bypass du scope client)"
 *   - grammaire incorrecte ("tous les analyses" -> "toutes les analyses")
 *   - "bypass du scope client" incomprehensible pour un admin non-tech
 *
 * Apres : "Accès à toutes les analyses de la plateforme, sans restriction par client"
 *   - grammaire correcte
 *   - vocabulaire metier compris par un admin fonctionnel
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Labels en francais correct pour chaque entite (avec bon accord).
     */
    private const LABELS = [
        'clients'          => 'clients',
        'missions'         => 'missions',
        'analyses'         => 'analyses d\'écarts',
        'plans-actions'    => 'plans d\'actions',
        'traitements'      => 'traitements DCP',
        'ecarts'           => 'écarts de conformité',
        'chartes'          => 'chartes',
        'registres-kyc'    => 'registres KYC',
        'questionnaires'   => 'questionnaires',
        'referentiels'     => 'référentiels',
        'matrice'          => 'matrices de collecte',
        'organigramme'     => 'organigrammes',
        'portefeuille'     => 'portefeuilles clients',
        'conversations'    => 'conversations',
        'documents'        => 'documents',
        'secteurs'         => 'secteurs d\'activité',
        'taches'           => 'tâches',
        'agents'           => 'agents IA',
        'ref-data'         => 'données de référence',
        'client-organisme' => 'organismes des clients',
        'client-documents' => 'documents clients',
    ];

    public function up(): void
    {
        foreach (self::LABELS as $entite => $libelleFrancais) {
            $permName = 'view-all-' . $entite;

            $description = "Accès à tous les {$libelleFrancais} de la plateforme, sans restriction par client "
                . "(destiné aux managers et administrateurs qui supervisent l'ensemble du portefeuille).";

            // Corriger l'accord tous/toutes selon le genre : la liste des feminins.
            $feminins = [
                'analyses d\'écarts', 'chartes', 'matrices de collecte', 'tâches',
                'données de référence', 'conversations', 'missions',
            ];
            if (in_array($libelleFrancais, $feminins, true)) {
                $description = str_replace('tous les ' . $libelleFrancais, 'toutes les ' . $libelleFrancais, $description);
            }

            DB::table('permissions')
                ->where('name', $permName)
                ->update([
                    'description' => $description,
                    'updated_at' => now(),
                ]);
        }

        // Vider le cache Spatie/permission pour propager immediatement.
        app()['cache']->forget(config('permission.cache.key', 'spatie.permission.cache'));
    }

    public function down(): void
    {
        // Rollback : remet l'ancienne description (bypass du scope client).
        foreach (array_keys(self::LABELS) as $entite) {
            $permName = 'view-all-' . $entite;
            DB::table('permissions')
                ->where('name', $permName)
                ->update([
                    'description' => 'Voir/gérer tous les ' . $entite . ' (bypass du scope client)',
                    'updated_at' => now(),
                ]);
        }

        app()['cache']->forget(config('permission.cache.key', 'spatie.permission.cache'));
    }
};
