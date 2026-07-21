<?php

/**
 * Interface RetrievalInterface — Contrat pour la recherche documentaire (RAG).
 *
 * L'implementation decide de la methode de recherche :
 * pgvector (similarite semantique) ou tsvector (full-text).
 */

namespace App\Contracts;

interface RetrievalInterface
{
    /**
     * Recherche les chunks de documents les plus pertinents pour une requete.
     *
     * @param string $requete Texte de la requete utilisateur
     * @param int|null $missionId Filtrer par mission (null = toutes)
     * @param int $limite Nombre max de chunks a retourner
     * @return array<array{contenu: string, document_id: int, document_titre: string, score: float, page: ?int}>
     */
    public function rechercher(string $requete, ?int $missionId = null, int $limite = 5): array;
}
