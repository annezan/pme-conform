<?php

/**
 * Phase 4 — Workflow de publication des questionnaires par ASC.
 *
 * Ajoute sur `questionnaires_generes` :
 *   - est_publie (bool) : true si le questionnaire est visible par les clients.
 *     Par defaut false : ASC doit publier explicitement chaque questionnaire
 *     apres l'avoir reviewé.
 *   - publie_le (timestamp) : date de publication.
 *   - publie_par (FK user) : utilisateur ASC qui a publie.
 *
 * Distinction avec `statut` :
 *   - `statut` (brouillon/envoye/rempli/valide) : cycle de vie cote client
 *     (remplissage et validation des reponses)
 *   - `est_publie` : visibilite cote client (decision ASC)
 *
 * Pour les questionnaires existants (avant Phase 4) on les marque publies
 * pour ne pas casser les flux en cours.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questionnaires_generes', function (Blueprint $table) {
            $table->boolean('est_publie')->default(false)->after('statut');
            $table->timestamp('publie_le')->nullable()->after('est_publie');
            $table->foreignId('publie_par')->nullable()->after('publie_le')->constrained('users')->nullOnDelete();
        });

        // Considerer les questionnaires existants comme publies pour ne pas
        // bloquer brutalement les clients actifs.
        DB::table('questionnaires_generes')->update([
            'est_publie' => true,
            'publie_le' => DB::raw('CURRENT_TIMESTAMP'),
        ]);
    }

    public function down(): void
    {
        Schema::table('questionnaires_generes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('publie_par');
            $table->dropColumn(['est_publie', 'publie_le']);
        });
    }
};
