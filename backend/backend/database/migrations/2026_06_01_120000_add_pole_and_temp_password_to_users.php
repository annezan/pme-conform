<?php

/**
 * Phase 2 du cahier des charges : pole utilisateur + mot de passe temporaire.
 *
 * Sur `client_user` (pivot) :
 *   - pole (string) : pole du user au sein de l'entreprise cliente.
 *     Un meme pole ne peut etre associe qu'a un seul user pour un client donne
 *     (contrainte unique partielle hors valeurs NULL).
 *
 * Sur `users` :
 *   - must_change_password (bool) : true si le mot de passe en base est temporaire
 *     et que l'utilisateur doit le changer a sa prochaine connexion. Le frontend
 *     redirige automatiquement vers /changer-mot-de-passe tant que ce flag est vrai.
 *   - mdp_temporaire_expire_le (datetime nullable) : date limite de validite du
 *     mot de passe temporaire. Apres expiration, le user doit demander un nouveau
 *     mot de passe via "Mot de passe oublie".
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_user', function (Blueprint $table) {
            $table->string('pole')->nullable()->after('user_id');
        });

        // Contrainte unique partielle : un meme pole ne peut etre attache qu'a UN
        // seul user pour un client donne. Les pivots sans pole (consultants ASC
        // rattaches a plusieurs clients) ne sont pas concernes.
        DB::statement('CREATE UNIQUE INDEX client_user_pole_unique ON client_user (client_id, pole) WHERE pole IS NOT NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('compte_valide');
            $table->timestamp('mdp_temporaire_expire_le')->nullable()->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['must_change_password', 'mdp_temporaire_expire_le']);
        });

        DB::statement('DROP INDEX IF EXISTS client_user_pole_unique');

        Schema::table('client_user', function (Blueprint $table) {
            $table->dropColumn('pole');
        });
    }
};
