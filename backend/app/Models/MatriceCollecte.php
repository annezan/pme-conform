<?php

/**
 * MatriceCollecte — Reponses du client a la matrice initiale (Methode 2,
 * etape 1). Une matrice par mission, contient les pieces de conviction
 * uploadees par le client + ses reponses libres ou structurees.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatriceCollecte extends Model
{
    protected $table = 'matrices_collecte';

    protected $fillable = [
        'mission_id', 'statut', 'reponses', 'reponses_libres',
        'envoyee_a', 'remise_a', 'validee_par', 'validee_at',
    ];

    protected function casts(): array
    {
        return [
            'reponses' => 'array',
            'envoyee_a' => 'datetime',
            'remise_a' => 'datetime',
            'validee_at' => 'datetime',
        ];
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function pieces(): HasMany
    {
        return $this->hasMany(MatriceCollectePiece::class);
    }

    public function validatrice(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validee_par');
    }
}
