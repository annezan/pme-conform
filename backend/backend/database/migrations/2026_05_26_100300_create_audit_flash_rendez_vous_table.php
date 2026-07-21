<?php

/**
 * Migration de la table audit_flash_rendez_vous.
 *
 * Enregistre les demandes de prise de rendez-vous declenchees apres
 * l'affichage du resultat d'un audit flash :
 *  - demande d'accompagnement AS Consulting
 *  - demande d'audit complet
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_flash_rendez_vous', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('mission_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('questionnaire_genere_id')->nullable()->constrained('questionnaires_generes')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // utilisateur qui a soumis
            $table->string('nom');
            $table->string('email');
            $table->string('telephone', 30)->nullable();
            $table->dateTime('creneau_souhaite')->nullable();
            $table->string('creneau_libelle')->nullable(); // ex "Lundi matin"
            $table->enum('type_demande', ['accompagnement', 'audit_complet']);
            $table->text('message')->nullable();
            $table->enum('statut', ['nouveau', 'contacte', 'planifie', 'realise', 'annule'])->default('nouveau');
            $table->text('notes_internes')->nullable();
            $table->timestamp('contacte_at')->nullable();
            $table->foreignId('assigne_a')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['statut', 'created_at']);
            $table->index('type_demande');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_flash_rendez_vous');
    }
};
