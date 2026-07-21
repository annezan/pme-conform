<?php

/**
 * Migration de reparation : cree document_chunks si elle n'existe pas.
 *
 * Sur les serveurs sans pgvector, la migration initiale create_document_chunks_table
 * echouait silencieusement (CREATE EXTENSION vector aborte la transaction PostgreSQL
 * meme avec try/catch). Cette migration reconstruit la table hors transaction.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (Schema::hasTable('document_chunks')) {
            // Deja presente : tenter seulement d'ajouter la colonne embedding si pgvector dispo
            $this->tenterAjouterEmbedding();
            return;
        }

        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->text('contenu');
            $table->integer('position');
            $table->integer('page')->nullable();
            $table->integer('taille_caracteres');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('document_id');
        });

        $this->tenterAjouterEmbedding();
    }

    private function tenterAjouterEmbedding(): void
    {
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        } catch (\Throwable $e) {
            Log::warning('pgvector absent — document_chunks restera sans colonne embedding (fallback full-text)', [
                'error' => $e->getMessage(),
            ]);
            return;
        }

        try {
            $dimensions = config('services.ollama.embedding_dimensions', 3072);
            DB::statement("ALTER TABLE document_chunks ADD COLUMN IF NOT EXISTS embedding vector({$dimensions})");
            DB::statement('CREATE INDEX IF NOT EXISTS document_chunks_embedding_idx ON document_chunks USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
        } catch (\Throwable $e) {
            Log::warning('Impossible d\'ajouter colonne embedding a document_chunks', ['error' => $e->getMessage()]);
        }
    }

    public function down(): void
    {
        // Ne rien faire : la suppression relevait de la migration d'origine.
    }
};
