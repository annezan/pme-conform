<?php

/**
 * Migration — Ajoute la colonne embedding a referentiel_chunks si pgvector est disponible.
 *
 * Cette migration s'execute hors de la transaction Laravel pour eviter
 * qu'une erreur pgvector mette la transaction en etat "aborted".
 * Si pgvector est absent, la migration est consideree comme reussie
 * et la recherche retombera sur le mode full-text PostgreSQL.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Desactive la transaction automatique de Laravel pour cette migration.
     * Permet d'absorber l'erreur pgvector sans abort transaction.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        } catch (\Throwable $e) {
            Log::warning('pgvector : extension non disponible, migration skip.', ['error' => $e->getMessage()]);
            return;
        }

        try {
            $dimensions = config('services.ollama.embedding_dimensions', 3072);
            DB::statement("ALTER TABLE referentiel_chunks ADD COLUMN IF NOT EXISTS embedding vector({$dimensions})");
            DB::statement('CREATE INDEX IF NOT EXISTS referentiel_chunks_embedding_idx ON referentiel_chunks USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
            Log::info('pgvector : colonne embedding ajoutee a referentiel_chunks.');
        } catch (\Throwable $e) {
            Log::warning('pgvector : impossible d\'ajouter la colonne embedding a referentiel_chunks.', ['error' => $e->getMessage()]);
        }
    }

    public function down(): void
    {
        try {
            DB::statement('DROP INDEX IF EXISTS referentiel_chunks_embedding_idx');
            DB::statement('ALTER TABLE referentiel_chunks DROP COLUMN IF EXISTS embedding');
        } catch (\Throwable $e) {
            Log::warning('Rollback embedding referentiel_chunks ignore.', ['error' => $e->getMessage()]);
        }
    }
};
