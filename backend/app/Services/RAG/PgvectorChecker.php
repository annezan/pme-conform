<?php

/**
 * Service PgvectorChecker — Verifie la disponibilite de pgvector.
 *
 * Le resultat est mis en cache pour la duree du processus PHP.
 * Utilise par le RetrievalService et le ProcessDocumentJob.
 */

namespace App\Services\RAG;

use Illuminate\Support\Facades\Schema;

class PgvectorChecker
{
    private ?bool $disponible = null;

    /**
     * Verifie si pgvector est installe et la colonne embedding existe.
     */
    public function estDisponible(): bool
    {
        if ($this->disponible === null) {
            $this->disponible = Schema::hasColumn('document_chunks', 'embedding');
        }

        return $this->disponible;
    }

    /**
     * Force le rechargement du statut (utile apres une migration).
     */
    public function reinitialiser(): void
    {
        $this->disponible = null;
    }
}
