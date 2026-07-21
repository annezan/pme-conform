<?php

/**
 * Permet d'attacher un questionnaire (Audit Flash libre) directement
 * a un client, sans passer par une mission.
 *  - mission_id devient nullable
 *  - ajout d'un client_id (nullable, FK vers clients) pour les questionnaires
 *    "libres" (Audit Flash en self-service).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // mission_id nullable (Postgres : on supprime la contrainte NOT NULL)
        DB::statement('ALTER TABLE questionnaires_generes ALTER COLUMN mission_id DROP NOT NULL');

        Schema::table('questionnaires_generes', function (Blueprint $table) {
            $table->foreignId('client_id')
                ->nullable()
                ->after('mission_id')
                ->constrained('clients')
                ->nullOnDelete();
            $table->index(['client_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::table('questionnaires_generes', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropIndex(['client_id', 'statut']);
            $table->dropColumn('client_id');
        });

        // Revenir a NOT NULL : seulement si toutes les lignes ont mission_id
        DB::statement('UPDATE questionnaires_generes SET mission_id = 0 WHERE mission_id IS NULL');
        DB::statement('ALTER TABLE questionnaires_generes ALTER COLUMN mission_id SET NOT NULL');
    }
};
