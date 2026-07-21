<?php

/**
 * Ajoute documents.client_id pour rattacher un document directement a un
 * client (entreprise), sans passer par une mission "Boite de reception".
 *
 * Justification : auparavant chaque upload via /mes-documents creait une
 * fausse mission "Boite de reception" rien que pour satisfaire la relation
 * Document -> Mission -> Client. Cela polluait la liste /missions et
 * generait des bugs (suppression de la boite par l'utilisateur, doublons
 * apres soft-delete). On rend le lien Document -> Client direct.
 *
 * Backfill : pour chaque document existant, on copie le client_id depuis sa
 * mission (en incluant les missions soft-deleted pour ne perdre aucun lien).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('client_id')
                ->nullable()
                ->after('mission_id')
                ->constrained('clients')
                ->nullOnDelete();
        });

        // Backfill : on derive le client_id depuis la mission existante.
        // On inclut les missions soft-deleted pour ne perdre aucun lien.
        DB::statement(<<<SQL
            UPDATE documents d
            SET client_id = m.client_id
            FROM missions m
            WHERE d.mission_id = m.id
              AND d.client_id IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });
    }
};
