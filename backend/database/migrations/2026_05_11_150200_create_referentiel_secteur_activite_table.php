<?php

/**
 * Création de la table pivot referentiel_secteur_activite.
 * 
 * Cette table établit la relation many-to-many entre
 * les référentiels et les secteurs d'activité normalisés.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referentiel_secteur_activite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referentiel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('secteur_activite_id')->constrained('secteurs_activite')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['referentiel_id', 'secteur_activite_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referentiel_secteur_activite');
    }
};
