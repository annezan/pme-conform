<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatriceCollectePiece extends Model
{
    protected $fillable = [
        'matrice_collecte_id',
        'document_id',
        'uploade_par',
        'pole_code',
        'item_code',
        'libelle',
        'chemin',
        'mime',
        'taille_octets',
    ];

    public function matrice(): BelongsTo
    {
        return $this->belongsTo(MatriceCollecte::class, 'matrice_collecte_id');
    }

    public function uploadeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploade_par');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
