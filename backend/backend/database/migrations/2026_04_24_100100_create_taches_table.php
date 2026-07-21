<?php

/**
 * taches : Taches confiees aux agents AS Consulting (admin/manager/consultant).
 *
 * Une tache est rattachee a un client + optionnellement une mission, et
 * assignee a un user ASC. Sert pour le pilotage interne (recherche IA,
 * preparation questionnaire, execution analyse, etc.).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('mission_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assignee_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assignee_par')->constrained('users');
            $table->string('titre');
            $table->text('description')->nullable();
            $table->enum('type', [
                'recherche_ia',
                'preparation_questionnaire',
                'envoi_matrice',
                'execution_analyse',
                'redaction_rapport',
                'revision_traitement',
                'autre',
            ])->default('autre');
            $table->enum('priorite', ['basse', 'normale', 'haute', 'urgente'])->default('normale');
            $table->enum('statut', ['a_faire', 'en_cours', 'bloquee', 'terminee', 'annulee'])->default('a_faire');
            $table->date('echeance')->nullable();
            $table->timestamp('demarree_a')->nullable();
            $table->timestamp('terminee_a')->nullable();
            $table->text('commentaire_cloture')->nullable();
            $table->timestamps();

            $table->index(['assignee_id', 'statut']);
            $table->index(['client_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taches');
    }
};
