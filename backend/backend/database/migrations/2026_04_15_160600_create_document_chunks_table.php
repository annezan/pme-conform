<?php

/**
 * Migration de la table document_chunks.
 *
 * Stocke les fragments de texte des documents avec leurs embeddings vectoriels.
 * Utilisé par le système RAG pour la recherche sémantique via pgvector.
 *
 * NOTE : La colonne embedding et son index sont ajoutés uniquement si
 * l'extension pgvector est disponible. Sinon, ils seront ajoutés
 * ultérieurement via une migration dédiée après installation de pgvector.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->text('contenu'); // Fragment de texte
            $table->integer('position'); // Ordre du chunk dans le document
            $table->integer('page')->nullable(); // Numéro de page source
            $table->integer('taille_caracteres'); // Nombre de caractères du chunk
            $table->json('metadata')->nullable(); // Section, titre de section, etc.
            $table->timestamps();

            $table->index('document_id');
        });

        // Tenter d'activer pgvector et d'ajouter la colonne embedding
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

            $dimensions = config('services.ollama.embedding_dimensions', 3072);
            DB::statement("ALTER TABLE document_chunks ADD COLUMN embedding vector({$dimensions})");
            DB::statement('CREATE INDEX document_chunks_embedding_idx ON document_chunks USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');

            Log::info('pgvector : extension activée et colonne embedding créée.');
        } catch (\Exception $e) {
            Log::warning('pgvector : extension non disponible. La colonne embedding sera ajoutée après installation de pgvector.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
