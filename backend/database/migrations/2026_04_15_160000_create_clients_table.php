<?php

/**
 * Migration de la table clients.
 *
 * Représente les entreprises clientes accompagnées par AS Consulting
 * dans leurs démarches de mise en conformité ARTCI.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('raison_sociale'); // Nom de l'entreprise cliente
            $table->string('sigle')->nullable(); // Acronyme ou nom court
            $table->string('secteur_activite')->nullable();
            $table->string('numero_registre_commerce')->nullable();
            $table->text('adresse')->nullable();
            $table->string('ville')->nullable();
            $table->string('pays')->default('Cote d Ivoire');
            $table->string('telephone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('site_web')->nullable();
            $table->string('contact_principal_nom')->nullable();
            $table->string('contact_principal_email')->nullable();
            $table->string('contact_principal_telephone', 20)->nullable();
            $table->string('contact_principal_poste')->nullable();
            $table->enum('statut', ['prospect', 'actif', 'inactif', 'archive'])->default('prospect');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Table pivot : association consultants ↔ clients
        Schema::create('client_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role_projet')->nullable(); // Rôle du consultant sur ce client
            $table->timestamps();

            $table->unique(['client_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_user');
        Schema::dropIfExists('clients');
    }
};
