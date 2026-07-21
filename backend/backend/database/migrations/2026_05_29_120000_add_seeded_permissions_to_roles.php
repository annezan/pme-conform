<?php

/**
 * Ajoute un champ `seeded_permissions` (JSON) sur la table `roles`.
 *
 * But : memoriser pour chaque role la liste des permissions deja seedees au
 * moins une fois par RolesAndPermissionsSeeder. Permet au re-seed de
 * n'attacher que les permissions VRAIMENT nouvelles (celles ajoutees au code
 * apres le precedent seed) sans jamais re-attacher celles qu'un administrateur
 * aurait retirees manuellement via /admin/permissions.
 *
 * Comportement attendu apres cette migration :
 *   - Roles existants : `seeded_permissions` est NULL, ce qui declenche au
 *     prochain seed une initialisation de l'historique sans aucune modification
 *     des permissions actuellement attachees. Ainsi les modifications passees
 *     via l'UI sont preservees.
 *   - Roles crees ulterieurement : `seeded_permissions` est rempli a la
 *     creation et mis a jour a chaque re-seed.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->json('seeded_permissions')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('seeded_permissions');
        });
    }
};
