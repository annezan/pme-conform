<?php

/**
 * Modèle DocumentChunk — Fragment de document pour le RAG.
 *
 * Chaque chunk contient un extrait de texte et son embedding vectoriel
 * stocké dans pgvector pour la recherche sémantique.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

class DocumentChunk extends Model
{
    use HasNeighbors;

    protected $fillable = [
        'document_id',
        'contenu',
        'position',
        'page',
        'taille_caracteres',
        'embedding',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => Vector::class,
            'metadata' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
