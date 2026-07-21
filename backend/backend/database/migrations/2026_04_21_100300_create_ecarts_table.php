<?php

/**
 * Migration de la table ecarts.
 *
 * Un ecart represente un manquement detecte entre une exigence
 * d'un referentiel et les documents fournis par le client.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecarts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analyse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('referentiel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('referentiel_chunk_id')->nullable()->constrained('referentiel_chunks')->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete(); // Document client source (si ecart partiel)
            $table->enum('gravite', ['critique', 'majeur', 'mineur', 'observation'])->default('majeur');
            $table->enum('categorie', [
                'gouvernance',
                'juridique',
                'technique',
                'organisationnelle',
                'documentaire',
                'autre',
            ])->default('autre');
            $table->enum('type_ecart', [
                'absence_totale',     // Aucune preuve trouvee
                'preuve_insuffisante', // Preuve partielle/floue
                'non_conformite',      // Preuve contredit l'exigence
                'obsolete',            // Document fourni obsolete
            ])->default('absence_totale');
            $table->string('titre');
            $table->text('exigence_referentiel'); // Extrait du referentiel
            $table->string('article_reference')->nullable();
            $table->text('description_ecart'); // Description redigee par le LLM
            $table->text('recommandation')->nullable();
            $table->text('extrait_document')->nullable(); // Extrait du doc client cite (si applicable)
            $table->decimal('score_similarite', 5, 4)->nullable(); // Score RAG
            $table->enum('statut_correction', [
                'ouvert',
                'en_cours',
                'traite',
                'accepte_par_client',
                'rejete',
            ])->default('ouvert');
            $table->foreignId('assigne_a')->nullable()->constrained('users')->nullOnDelete();
            $table->date('echeance_correction')->nullable();
            $table->text('notes_consultant')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['analyse_id', 'gravite']);
            $table->index('statut_correction');
            $table->index('categorie');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecarts');
    }
};
