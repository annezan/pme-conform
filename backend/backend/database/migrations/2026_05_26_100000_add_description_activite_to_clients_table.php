<?php

/**
 * Ajout du champ description_activite sur la table clients.
 *
 * Description longue obligatoire de l'activite de l'entreprise cliente,
 * utilisee par le moteur d'analyse pour cibler les referentiels pertinents
 * et enrichir le contexte fourni au LLM lors de la generation des ecarts.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Etape 1 : ajout en nullable pour permettre le backfill des lignes existantes.
        Schema::table('clients', function (Blueprint $table) {
            $table->text('description_activite')->nullable()->after('sigle');
        });

        // Etape 2 : backfill chaine vide sur les lignes existantes pour respecter la
        //           future contrainte NOT NULL sans intervention manuelle.
        DB::table('clients')
            ->whereNull('description_activite')
            ->update(['description_activite' => '']);

        // Etape 3 : passage en NOT NULL.
        Schema::table('clients', function (Blueprint $table) {
            $table->text('description_activite')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('description_activite');
        });
    }
};
