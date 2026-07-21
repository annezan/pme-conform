<?php

/**
 * Migration de la table modules.
 *
 * Gère les modules métier dynamiques de la plateforme.
 * Chaque module est un Service Provider Laravel indépendant
 * activable/désactivable via cette table.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // Ex: conformite-artci
            $table->string('nom'); // Nom affiché
            $table->text('description')->nullable();
            $table->string('version')->default('1.0.0');
            $table->string('icone')->nullable(); // Classe d'icône pour l'interface
            $table->string('couleur')->nullable(); // Couleur thème du module
            $table->string('service_provider'); // Classe PHP du Service Provider
            $table->string('namespace'); // Namespace PHP du module
            $table->string('chemin'); // Chemin relatif du dossier du module
            $table->boolean('is_active')->default(false);
            $table->boolean('is_core')->default(false); // Module du noyau (non désactivable)
            $table->json('configuration')->nullable(); // Configuration spécifique au module
            $table->json('dependances')->nullable(); // Modules prérequis
            $table->integer('ordre_affichage')->default(0);
            $table->timestamp('active_depuis')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
