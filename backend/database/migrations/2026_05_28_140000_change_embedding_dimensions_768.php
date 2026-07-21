<?php

/**
 * Recree les colonnes `embedding` de document_chunks et referentiel_chunks
 * a la dimension 768 (modele nomic-embed-text) au lieu de 3072 (llama3.2).
 *
 * Tous les embeddings existants sont perdus -> il faut reindexer documents
 * et referentiels apres cette migration (commande analyse:rejouer ou
 * reindexation manuelle).
 *
 * Dimensions lues depuis PGVECTOR_DIMENSIONS dans config/services.php
 * pour garder un seul point de verite.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        } catch (\Throwable $e) {
            Log::warning('pgvector : extension non disponible, migration skip.', ['error' => $e->getMessage()]);

            return;
        }

        $dim = (int) config('services.ollama.embedding_dimensions', 768);

        foreach (['document_chunks', 'referentiel_chunks'] as $table) {
            try {
                DB::statement("DROP INDEX IF EXISTS {$table}_embedding_idx");
                DB::statement("ALTER TABLE {$table} DROP COLUMN IF EXISTS embedding");
                DB::statement("ALTER TABLE {$table} ADD COLUMN embedding vector({$dim})");
                DB::statement("CREATE INDEX {$table}_embedding_idx ON {$table} USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)");
                Log::info("pgvector : colonne embedding de {$table} recreee en vector({$dim}).");
            } catch (\Throwable $e) {
                Log::warning("pgvector : impossible de recreer embedding de {$table}.", ['error' => $e->getMessage()]);
            }
        }
    }

    public function down(): void
    {
        // Rollback : remet la dimension a 3072 (taille llama3.2)
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        } catch (\Throwable $e) {
            return;
        }

        foreach (['document_chunks', 'referentiel_chunks'] as $table) {
            try {
                DB::statement("DROP INDEX IF EXISTS {$table}_embedding_idx");
                DB::statement("ALTER TABLE {$table} DROP COLUMN IF EXISTS embedding");
                DB::statement("ALTER TABLE {$table} ADD COLUMN embedding vector(3072)");
                DB::statement("CREATE INDEX {$table}_embedding_idx ON {$table} USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)");
            } catch (\Throwable $e) {
                Log::warning("Rollback embedding {$table} ignore.", ['error' => $e->getMessage()]);
            }
        }
    }
};
