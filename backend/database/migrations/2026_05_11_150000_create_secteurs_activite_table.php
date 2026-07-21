<?php

/**
 * Création de la table secteurs_activite.
 * 
 * Cette table stocke les secteurs d'activité de manière normalisée
 * pour éviter la redondance et permettre une gestion centralisée.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secteurs_activite', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->text('description')->nullable();
            $table->string('code')->nullable()->unique();
            $table->boolean('is_actif')->default(true);
            
            $table->auditColumns();
            
            $table->timestamps();
            $table->softDeletes();

            $table->index('nom');
            $table->index('is_actif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secteurs_activite');
    }
};
