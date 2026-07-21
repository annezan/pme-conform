<?php

/**
 * Migration de la table analyses.
 *
 * Une analyse est une execution du moteur de detection d'ecarts
 * sur les documents d'une mission par rapport a un ou plusieurs referentiels.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lancee_par')->constrained('users');
            $table->string('reference', 32)->unique(); // Ex: ANA-2026-001
            $table->string('titre');
            $table->enum('statut', [
                'en_attente',   // Creee, en file
                'en_cours',     // Pipeline IA en execution
                'terminee',     // Succes
                'erreur',       // Echec
                'annulee',
            ])->default('en_attente');
            $table->json('referentiels_ids'); // [1,2,3] referentiels utilises
            $table->json('documents_ids');    // [10,11,12] documents client analyses
            $table->unsignedInteger('nb_exigences_verifiees')->default(0);
            $table->unsignedInteger('nb_ecarts_critiques')->default(0);
            $table->unsignedInteger('nb_ecarts_majeurs')->default(0);
            $table->unsignedInteger('nb_ecarts_mineurs')->default(0);
            $table->decimal('score_conformite', 5, 2)->nullable(); // 0-100
            $table->json('synthese')->nullable(); // Resume agrege (JSON structure)
            $table->text('commentaire_ia')->nullable(); // Synthese redigee par le LLM
            $table->string('rapport_word_path')->nullable(); // storage path du .docx genere
            $table->timestamp('demarree_a')->nullable();
            $table->timestamp('terminee_a')->nullable();
            $table->text('erreur_message')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['mission_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analyses');
    }
};
