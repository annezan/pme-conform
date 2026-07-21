<?php

/**
 * Migration de la table agents.
 *
 * Chaque agent IA est défini ici avec son prompt système,
 * son module d'appartenance et sa configuration.
 * Les prompts sont modifiables sans redéploiement.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug')->unique(); // Ex: analyse-conformite
            $table->string('nom');
            $table->text('description')->nullable();
            $table->text('prompt_systeme'); // Prompt système envoyé au LLM
            $table->string('icone')->nullable();
            $table->string('couleur')->nullable();
            $table->enum('type', [
                'conversationnel', // Chat interactif
                'analytique',      // Analyse de documents
                'generateur',      // Génération de documents
                'veille',          // Surveillance et alertes
                'assistant',       // Assistant général
            ])->default('conversationnel');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_core')->default(false); // Agent transversal du noyau
            $table->string('modele_llm')->nullable(); // Surcharge du modèle par défaut
            $table->integer('max_tokens')->nullable(); // Surcharge max tokens
            $table->float('temperature')->default(0.7);
            $table->json('configuration')->nullable(); // Paramètres spécifiques
            $table->json('permissions_requises')->nullable(); // Permissions nécessaires pour utiliser l'agent
            $table->integer('ordre_affichage')->default(0);
            $table->timestamps();

            $table->index('module_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
