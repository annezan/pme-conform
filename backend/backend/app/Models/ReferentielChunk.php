<?php

/**
 * Modele ReferentielChunk — Fragment d'un referentiel pour le RAG.
 *
 * Chaque chunk represente typiquement un article ou une exigence du texte legal.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

class ReferentielChunk extends Model
{
    use HasNeighbors;

    protected $fillable = [
        'referentiel_id',
        'contenu',
        'position',
        'page',
        'article_reference',
        'categorie_exigence',
        'theme_dcp',
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

    public function referentiel(): BelongsTo
    {
        return $this->belongsTo(Referentiel::class);
    }
}
