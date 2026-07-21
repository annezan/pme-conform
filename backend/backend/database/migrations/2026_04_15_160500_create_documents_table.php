<?php

/**
 * Migration de la table documents.
 *
 * Gère les documents uploadés et générés dans la plateforme.
 * Les fichiers physiques sont gérés par Spatie Media Library.
 * Le contenu est chiffré AES-256 au repos.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('nom_fichier_original');
            $table->string('type_mime');
            $table->bigInteger('taille_octets');
            $table->enum('type', [
                'document_client',   // Fourni par le client
                'rapport_audit',     // Généré : rapport d'audit
                'politique',         // Généré : politique de confidentialité
                'registre',          // Généré : registre des traitements
                'aipd',              // Généré : analyse d'impact
                'courrier_artci',    // Généré : courrier pour l'ARTCI
                'charte',            // Généré : charte
                'modele',            // Template de document
                'autre',
            ])->default('document_client');
            $table->enum('statut', [
                'en_attente',        // Upload en cours ou en attente de traitement
                'en_traitement',     // Extraction de texte / indexation en cours
                'indexe',            // Contenu extrait et indexé dans pgvector
                'erreur',            // Erreur lors du traitement
                'archive',
            ])->default('en_attente');
            $table->boolean('is_confidentiel')->default(true);
            $table->text('contenu_extrait')->nullable(); // Texte brut extrait du document
            $table->string('hash_fichier')->nullable(); // SHA-256 pour détecter les doublons
            $table->json('metadata')->nullable(); // Nombre de pages, langue détectée, etc.
            $table->timestamps();
            $table->softDeletes();

            $table->index(['mission_id', 'type']);
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
