<?php

/**
 * Ajoute le champ `compte_valide` (boolean) sur la table `users`.
 *
 * But : tracer la validation administrative initiale d'un compte inscrit via
 * /inscription. Distinction semantique avec `is_active` :
 *
 *   - compte_valide : le compte a-t-il PASSE la validation initiale par ASC ?
 *     Initialement false a l'inscription. Une fois mis a true par un admin,
 *     ne redevient jamais false.
 *
 *   - is_active : le compte est-il ACTUELLEMENT actif ? Peut etre passe a
 *     false a tout moment par un admin pour suspendre le compte.
 *
 * La connexion exige les DEUX : compte_valide = true ET is_active = true.
 *
 * Comportement de cette migration :
 *   - Pour les comptes EXISTANTS : compte_valide = true (on considere les
 *     comptes deja crees comme valides, sinon plus personne ne pourrait se
 *     connecter).
 *   - Pour les NOUVEAUX comptes : compte_valide = false par defaut.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('compte_valide')->default(false)->after('is_active');
            $table->timestamp('valide_le')->nullable()->after('compte_valide');
            $table->foreignId('valide_par')->nullable()->after('valide_le')->constrained('users')->nullOnDelete();
        });

        // Marquer les comptes existants comme valides pour ne pas casser l'auth.
        DB::table('users')->update([
            'compte_valide' => true,
            'valide_le' => DB::raw('CURRENT_TIMESTAMP'),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('valide_par');
            $table->dropColumn(['compte_valide', 'valide_le']);
        });
    }
};
