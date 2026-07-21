<?php

/**
 * One-shot : attache TOUTES les permissions actives au role admin existant.
 *
 * Contexte : historiquement, l'admin recevait uniquement une trentaine de
 * permissions umbrella (manage-* renommees en view-all-*) et beneficiait
 * d'un bypass systeme dans Gate::before pour couvrir le reste. L'UI
 * /admin/permissions affichait donc des cases decochees alors que l'admin
 * pouvait tout faire, ce qui est trompeur.
 *
 * Cette migration :
 *   1. Charge toutes les permissions actives (is_active = true)
 *   2. Les attache TOUTES au role admin via syncWithoutDetaching
 *   3. Met a jour l'historique seeded_permissions du role admin pour que
 *      la logique additive-pure du seeder ne les considere pas comme
 *      "nouvelles" au prochain run
 *   4. Vide le cache Spatie
 *
 * Le bypass Gate::before reste en place (ceinture + bretelles) : si un
 * admin decoche une permission dans l'UI, il reste couvert par le bypass.
 * Mais dans le cas nominal, les cases sont maintenant fideles a la realite.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $admin = DB::table('roles')->where('name', 'admin')->first();
        if (! $admin) {
            // Pas de role admin : rien a faire (fresh install non seedee).
            return;
        }

        // 1) Toutes les permissions actives.
        $permissionIds = DB::table('permissions')
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        if (empty($permissionIds)) {
            return;
        }

        // 2) Attache tout au role admin (syncWithoutDetaching : ne casse pas
        //    les liens existants, ne genere pas de doublon car il y a une
        //    contrainte unique (role_id, permission_id)).
        $now = now();
        $rows = [];
        foreach ($permissionIds as $pid) {
            $rows[] = [
                'role_id' => $admin->id,
                'permission_id' => $pid,
            ];
        }

        // Insertion en batch avec gestion des doublons (postgres : ON CONFLICT DO NOTHING)
        // La table est role_has_permissions (Spatie).
        foreach (array_chunk($rows, 200) as $batch) {
            DB::table('role_has_permissions')->insertOrIgnore($batch);
        }

        // 3) Met a jour seeded_permissions : liste tous les noms de permissions
        //    attaches pour que le seeder additif-pur ne les considere pas comme
        //    "nouvelles" au prochain re-run.
        $tousLesNoms = DB::table('permissions')
            ->whereIn('id', $permissionIds)
            ->pluck('name')
            ->all();

        DB::table('roles')->where('id', $admin->id)->update([
            'seeded_permissions' => json_encode(array_values(array_unique($tousLesNoms))),
            'updated_at' => $now,
        ]);

        // 4) Cache Spatie
        app()['cache']->forget(config('permission.cache.key', 'spatie.permission.cache'));
    }

    public function down(): void
    {
        // Rollback : impossible de savoir quelles permissions etaient attachees
        // avant l'installation de cette migration. On ne detache rien pour ne
        // pas casser l'admin. Si vraiment necessaire, l'operateur peut le faire
        // via /admin/permissions ou en re-jouant le seeder d'origine.
    }
};
