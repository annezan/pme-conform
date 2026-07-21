<?php

/**
 * Renomme les 22 permissions "manage-*" bypass-scope en "view-all-*".
 *
 * Contexte : les permissions manage-* etaient ambigues. Deux familles
 * coexistaient sous le meme prefixe :
 *
 *   1. Vraie gestion administrative (manage-users, manage-roles, ...) —
 *      elles gardent leur nom, c'est semantiquement correct.
 *
 *   2. Bypass de scope client (manage-clients, manage-missions, ...) —
 *      elles ne "gerent" rien, elles retirent juste la restriction
 *      "seulement mes clients" dans les policies. Le nom manage-* laisse
 *      croire que cocher view + create + update + delete + manage est
 *      redondant, alors que manage a un role different et complementaire.
 *
 * Cette migration renomme UNIQUEMENT la famille 2 vers view-all-*
 * pour lever l'ambiguite. Le nom exprime enfin ce que la permission fait :
 * "voir/agir sur TOUS les X (bypass du scope client)".
 *
 * Implementation :
 *   - UPDATE dans la table permissions (le champ 'name' change, les IDs restent).
 *   - Le pivot role_has_permissions conserve ses liens (relation par id).
 *   - Update l'historique 'seeded_permissions' des roles pour ne pas
 *     re-attacher les anciens noms au prochain re-seed.
 *   - Le code (policies, controllers, seeders) est mis a jour en parallele
 *     dans cette meme livraison.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Correspondance ancien nom -> nouveau nom.
     * Ordonnee pour eviter les conflits potentiels lors de UPDATE en cascade.
     */
    private const RENAMES = [
        'manage-clients'               => 'view-all-clients',
        'manage-missions'              => 'view-all-missions',
        'manage-analyses'              => 'view-all-analyses',
        'manage-plans-actions'         => 'view-all-plans-actions',
        // Note : manage-plans-actions-items reste inchangé — c'est une vraie
        // permission CRUD granulaire (autorise le client_admin a mettre a jour
        // les items du plan), pas un bypass scope.
        'manage-traitements'           => 'view-all-traitements',
        'manage-ecarts'                => 'view-all-ecarts',
        'manage-chartes'               => 'view-all-chartes',
        'manage-registres-kyc'         => 'view-all-registres-kyc',
        'manage-questionnaires'        => 'view-all-questionnaires',
        'manage-referentiels'          => 'view-all-referentiels',
        'manage-matrice'               => 'view-all-matrice',
        'manage-organigramme'          => 'view-all-organigramme',
        'manage-portefeuille'          => 'view-all-portefeuille',
        'manage-conversations'         => 'view-all-conversations',
        'manage-documents'             => 'view-all-documents',
        'manage-secteurs'              => 'view-all-secteurs',
        'manage-taches'                => 'view-all-taches',
        'manage-agents'                => 'view-all-agents',
        'manage-ref-data'              => 'view-all-ref-data',
        'manage-client-organisme'      => 'view-all-client-organisme',
        'manage-client-documents'      => 'view-all-client-documents',
    ];

    public function up(): void
    {
        // 1) Renommer dans la table permissions (le champ name).
        //    Les descriptions sont aussi mises a jour pour refleter la nouvelle
        //    semantique : "Voir tous les X (bypass scope)".
        foreach (self::RENAMES as $ancien => $nouveau) {
            // Si la nouvelle existe deja (double-run), on saute pour eviter unique-conflict.
            $existeNouveau = DB::table('permissions')->where('name', $nouveau)->exists();
            if ($existeNouveau) {
                // Cas rare : le nouveau existe deja, on supprime l'ancien pour eviter le duplicat
                DB::table('permissions')->where('name', $ancien)->delete();
                continue;
            }
            DB::table('permissions')
                ->where('name', $ancien)
                ->update([
                    'name' => $nouveau,
                    'description' => 'Voir/gérer tous les ' . self::extraireEntite($nouveau) . ' (bypass du scope client)',
                    'updated_at' => now(),
                ]);
        }

        // 2) Mettre a jour l'historique seeded_permissions sur les roles.
        //    Ce champ (JSON) memorise quelles permissions ont deja ete seedees
        //    pour eviter de les ré-attacher apres un decochage manuel. On
        //    remplace les anciens noms par les nouveaux dans le tableau.
        $roles = DB::table('roles')->select('id', 'seeded_permissions')->get();
        foreach ($roles as $role) {
            if (empty($role->seeded_permissions)) continue;
            $hist = is_string($role->seeded_permissions)
                ? (json_decode($role->seeded_permissions, true) ?: [])
                : $role->seeded_permissions;
            if (! is_array($hist) || empty($hist)) continue;

            $nouvelHist = array_map(fn ($p) => self::RENAMES[$p] ?? $p, $hist);
            $nouvelHist = array_values(array_unique($nouvelHist));

            DB::table('roles')->where('id', $role->id)->update([
                'seeded_permissions' => json_encode($nouvelHist),
                'updated_at' => now(),
            ]);
        }

        // 3) Vider le cache Spatie/permission pour que les nouveaux noms soient
        //    immediatement effectifs cote application.
        app()['cache']->forget(config('permission.cache.key', 'spatie.permission.cache'));
    }

    public function down(): void
    {
        // Rollback : renomme dans l'autre sens.
        $inverse = array_flip(self::RENAMES);
        foreach ($inverse as $nouveau => $ancien) {
            $existeAncien = DB::table('permissions')->where('name', $ancien)->exists();
            if ($existeAncien) {
                DB::table('permissions')->where('name', $nouveau)->delete();
                continue;
            }
            DB::table('permissions')->where('name', $nouveau)->update([
                'name' => $ancien,
                'description' => 'Gerer les ' . self::extraireEntite($ancien),
                'updated_at' => now(),
            ]);
        }

        $roles = DB::table('roles')->select('id', 'seeded_permissions')->get();
        foreach ($roles as $role) {
            if (empty($role->seeded_permissions)) continue;
            $hist = is_string($role->seeded_permissions)
                ? (json_decode($role->seeded_permissions, true) ?: [])
                : $role->seeded_permissions;
            if (! is_array($hist)) continue;

            $nouvelHist = array_map(fn ($p) => $inverse[$p] ?? $p, $hist);
            $nouvelHist = array_values(array_unique($nouvelHist));

            DB::table('roles')->where('id', $role->id)->update([
                'seeded_permissions' => json_encode($nouvelHist),
                'updated_at' => now(),
            ]);
        }

        app()['cache']->forget(config('permission.cache.key', 'spatie.permission.cache'));
    }

    /** Extrait le nom d'entite pour reconstruire une description propre. */
    private static function extraireEntite(string $permName): string
    {
        // "view-all-plans-actions" -> "plans-actions"
        return preg_replace('/^(view-all|manage)-/', '', $permName);
    }
};
