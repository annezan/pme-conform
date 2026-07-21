<?php

/**
 * Rend la colonne description obligatoire sur secteurs_activite.
 *
 * La description est utilisee par le moteur d'analyse pour reconcilier
 * le secteur d'un client avec les referentiels applicables : elle ne peut
 * plus rester vide.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill : les anciens secteurs sans description recoivent une chaine vide
        // pour respecter la contrainte NOT NULL.
        DB::table('secteurs_activite')
            ->whereNull('description')
            ->update(['description' => '']);

        Schema::table('secteurs_activite', function (Blueprint $table) {
            $table->text('description')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('secteurs_activite', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });
    }
};
