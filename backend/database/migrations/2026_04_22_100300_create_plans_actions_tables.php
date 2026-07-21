<?php

/**
 * Plans d'actions : proposes par le consultant ASC suite a une analyse
 * d'ecarts, acceptes et executes par le client.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('analyse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 32)->unique(); // PA-2026-001
            $table->string('titre');
            $table->text('objectif')->nullable();
            $table->foreignId('propose_par')->constrained('users');
            $table->enum('statut', ['propose', 'accepte_client', 'en_cours', 'cloture', 'rejete'])->default('propose');
            $table->date('date_debut_prevue')->nullable();
            $table->date('date_fin_prevue')->nullable();
            $table->timestamp('accepte_le')->nullable();
            $table->foreignId('accepte_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cloture_le')->nullable();
            $table->text('commentaire_cloture')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'statut']);
            $table->index('analyse_id');
        });

        Schema::create('plan_action_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_action_id')->constrained('plans_actions')->cascadeOnDelete();
            $table->foreignId('ecart_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('position')->default(0);
            $table->string('titre');
            $table->text('description')->nullable();
            $table->enum('priorite', ['p1', 'p2', 'p3', 'p4'])->default('p2');
            $table->enum('statut', ['a_faire', 'en_cours', 'termine', 'bloque'])->default('a_faire');
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('echeance')->nullable();
            $table->timestamp('termine_le')->nullable();
            $table->text('notes_client')->nullable();
            $table->text('notes_consultant')->nullable();
            $table->timestamps();

            $table->index(['plan_action_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_action_items');
        Schema::dropIfExists('plans_actions');
    }
};
