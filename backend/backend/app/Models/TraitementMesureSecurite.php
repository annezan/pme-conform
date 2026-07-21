<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TraitementMesureSecurite extends Model
{
    protected $table = 'traitement_mesures_securite';

    protected $fillable = ['traitement_id', 'categorie', 'description'];

    public function traitement(): BelongsTo
    {
        return $this->belongsTo(Traitement::class);
    }
}
