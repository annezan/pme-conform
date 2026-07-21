<?php

/**
 * Migration des tables conversations et messages.
 *
 * Gère l'historique des échanges entre utilisateurs et agents IA.
 * Chaque conversation est liée à un agent et optionnellement à une mission.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mission_id')->nullable()->constrained()->nullOnDelete();
            $table->string('titre')->nullable();
            $table->enum('statut', ['active', 'archivee', 'supprimee'])->default('active');
            $table->json('contexte')->nullable(); // Métadonnées de session
            $table->timestamps();

            $table->index(['user_id', 'agent_id']);
            $table->index('mission_id');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant', 'system']);
            $table->text('contenu');
            $table->text('contenu_pseudonymise')->nullable(); // Version envoyée au LLM
            $table->json('metadata')->nullable(); // Tokens utilisés, temps de réponse, etc.
            $table->json('sources')->nullable(); // Documents RAG utilisés pour la réponse
            $table->integer('tokens_entree')->nullable();
            $table->integer('tokens_sortie')->nullable();
            $table->integer('duree_ms')->nullable(); // Temps de génération en ms
            $table->timestamps();

            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
