<?php

/**
 * Service RetrievalService — Recherche documentaire pour le RAG.
 *
 * Deux modes de recherche :
 * 1. Semantique (pgvector) : recherche par similarite cosinus des embeddings
 * 2. Full-text (fallback) : recherche PostgreSQL tsvector avec stemming francais
 *
 * Le mode est selectionne automatiquement selon la disponibilite de pgvector.
 */

namespace App\Services\RAG;

use App\Contracts\LLMConnectorInterface;
use App\Contracts\RetrievalInterface;
use App\Models\DocumentChunk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pgvector\Laravel\Distance;

class RetrievalService implements RetrievalInterface
{
    public function __construct(
        private LLMConnectorInterface $llm,
        private PgvectorChecker $pgvectorChecker,
    ) {}

    public function rechercher(string $requete, ?int $missionId = null, int $limite = 5): array
    {
        if ($this->pgvectorChecker->estDisponible()) {
            return $this->rechercheSemantiqueVector($requete, $missionId, $limite);
        }

        return $this->rechercheFullText($requete, $missionId, $limite);
    }

    /**
     * Recherche par similarite cosinus via pgvector.
     */
    private function rechercheSemantiqueVector(string $requete, ?int $missionId, int $limite): array
    {
        try {
            $embedding = $this->llm->genererEmbedding($requete);

            $query = DocumentChunk::query()
                ->nearestNeighbors('embedding', $embedding, Distance::Cosine)
                ->join('documents', 'document_chunks.document_id', '=', 'documents.id')
                ->where('documents.statut', 'indexe')
                ->where('documents.deleted_at', null);

            if ($missionId) {
                $query->where('documents.mission_id', $missionId);
            }

            $chunks = $query
                ->take($limite)
                ->select([
                    'document_chunks.contenu',
                    'document_chunks.document_id',
                    'document_chunks.page',
                    'documents.titre as document_titre',
                ])
                ->get();

            return $chunks->map(fn ($chunk, $index) => [
                'contenu' => $chunk->contenu,
                'document_id' => $chunk->document_id,
                'document_titre' => $chunk->document_titre,
                'score' => 1.0 - ($index * 0.1), // Score approximatif base sur l'ordre
                'page' => $chunk->page,
            ])->toArray();

        } catch (\Throwable $e) {
            Log::warning('RAG : erreur recherche semantique, fallback full-text', [
                'error' => $e->getMessage(),
            ]);

            return $this->rechercheFullText($requete, $missionId, $limite);
        }
    }

    /**
     * Recherche full-text PostgreSQL avec stemming francais (fallback).
     */
    private function rechercheFullText(string $requete, ?int $missionId, int $limite): array
    {
        $query = DB::table('document_chunks')
            ->join('documents', 'document_chunks.document_id', '=', 'documents.id')
            ->whereRaw(
                "to_tsvector('french', document_chunks.contenu) @@ plainto_tsquery('french', ?)",
                [$requete]
            )
            ->where('documents.statut', 'indexe')
            ->whereNull('documents.deleted_at');

        if ($missionId) {
            $query->where('documents.mission_id', $missionId);
        }

        $chunks = $query
            ->orderByRaw("ts_rank(to_tsvector('french', document_chunks.contenu), plainto_tsquery('french', ?)) DESC", [$requete])
            ->take($limite)
            ->select([
                'document_chunks.contenu',
                'document_chunks.document_id',
                'document_chunks.page',
                'documents.titre as document_titre',
                DB::raw("ts_rank(to_tsvector('french', document_chunks.contenu), plainto_tsquery('french', " . DB::connection()->getPdo()->quote($requete) . ")) as score"),
            ])
            ->get();

        return $chunks->map(fn ($chunk) => [
            'contenu' => $chunk->contenu,
            'document_id' => $chunk->document_id,
            'document_titre' => $chunk->document_titre,
            'score' => (float) $chunk->score,
            'page' => $chunk->page,
        ])->toArray();
    }
}
