<?php

/**
 * Migration de la table analyse_versions.
 *
 * Conserve un snapshot complet de chaque analyse avant qu'elle ne soit
 * relancee via le bouton "Refaire l'analyse". Permet la consultation de
 * l'historique, la comparaison entre versions et la conservation des
 * preuves justificatives associees a chaque iteration.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analyse_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analyse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('numero_version'); // 1, 2, 3... compteur par analyse
            $table->string('motif')->nullable();        // libelle libre saisi par l'auteur
            $table->string('statut');                   // snapshot du statut au moment du gel
            $table->decimal('score_conformite', 5, 2)->nullable();
            $table->unsignedInteger('nb_exigences_total')->default(0);
            $table->unsignedInteger('nb_exigences_verifiees')->default(0);
            $table->unsignedInteger('nb_ecarts_critiques')->default(0);
            $table->unsignedInteger('nb_ecarts_majeurs')->default(0);
            $table->unsignedInteger('nb_ecarts_mineurs')->default(0);
            $table->json('referentiels_ids')->nullable();
            $table->json('documents_ids')->nullable();
            $table->json('questionnaires_ids')->nullable();
            $table->json('synthese')->nullable();          // JSON synthese figee
            $table->longText('ecarts_snapshot')->nullable(); // JSON serialise des ecarts
            $table->json('preuves_snapshot')->nullable();    // [{ecart_id, document_id, ...}]
            $table->string('rapport_word_path')->nullable(); // copie figee du .docx
            $table->text('commentaire_ia')->nullable();
            $table->timestamp('demarree_a')->nullable();
            $table->timestamp('terminee_a')->nullable();
            $table->timestamps();

            $table->unique(['analyse_id', 'numero_version']);
            $table->index(['analyse_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analyse_versions');
    }
};
