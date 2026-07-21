<?php

/**
 * Migration de la table audit_logs.
 *
 * Journal d'audit complet de toutes les actions sur la plateforme.
 * Complète Spatie Activity Log avec des champs spécifiques
 * aux exigences de conformité (IP, données concernées, résultat).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); // Ex: document.upload, agent.requete, auth.connexion
            $table->string('categorie')->nullable(); // auth, document, agent, admin, etc.
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('auditable_type')->nullable(); // Modèle concerné (polymorphe)
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('anciennes_valeurs')->nullable(); // Avant modification
            $table->json('nouvelles_valeurs')->nullable(); // Après modification
            $table->enum('resultat', ['succes', 'echec', 'erreur'])->default('succes');
            $table->text('message_erreur')->nullable();
            $table->json('metadata')->nullable(); // Données supplémentaires
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'action']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('categorie');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
