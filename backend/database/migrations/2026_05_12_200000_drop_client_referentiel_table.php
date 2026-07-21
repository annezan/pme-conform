<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supprimer la table pivot client_referentiel
     * car les référentiels sont maintenant liés aux secteurs d'activité
     */
    public function up(): void
    {
        Schema::dropIfExists('client_referentiel');
    }

    public function down(): void
    {
        Schema::create('client_referentiel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('referentiel_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }
};
