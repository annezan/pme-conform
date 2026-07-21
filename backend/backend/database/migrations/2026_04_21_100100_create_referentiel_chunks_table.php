<?php

/**
 * Migration de la table referentiel_chunks.
 *
 * Stocke les fragments de texte des referentiels separement des documents clients.
 * La colonne embedding (pgvector) est ajoutee par une migration dediee si
 * l'extension pgvector est disponible.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referentiel_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referentiel_id')->constrained()->cascadeOnDelete();
            $table->text('contenu');
            $table->integer('position');
            $table->integer('page')->nullable();
            $table->string('article_reference', 64)->nullable();
            $table->enum('categorie_exigence', [
                'gouvernance',
                'juridique',
                'technique',
                'organisationnelle',
                'documentaire',
                'autre',
            ])->nullable();
            $table->integer('taille_caracteres');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('referentiel_id');
            $table->index('categorie_exigence');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referentiel_chunks');
    }
};
