<?php

/**
 * Migration de la table missions.
 *
 * Une mission représente un dossier de conformité pour un client.
 * Tous les documents, conversations et analyses sont rattachés à une mission.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('responsable_id')->constrained('users'); // Consultant responsable
            $table->string('reference')->unique(); // Ex: MISS-2026-001
            $table->string('titre');
            $table->text('description')->nullable();
            $table->enum('type', [
                'audit_conformite',
                'accompagnement',
                'formation',
                'aipd',
                'declaration_artci',
                'autre',
            ])->default('audit_conformite');
            $table->enum('statut', [
                'brouillon',
                'en_cours',
                'en_revue',
                'termine',
                'archive',
            ])->default('brouillon');
            $table->enum('priorite', ['basse', 'normale', 'haute', 'urgente'])->default('normale');
            $table->date('date_debut')->nullable();
            $table->date('date_echeance')->nullable();
            $table->date('date_cloture')->nullable();
            $table->decimal('progression', 5, 2)->default(0); // Pourcentage 0-100
            $table->text('notes_internes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missions');
    }
};
