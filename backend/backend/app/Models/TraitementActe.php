<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TraitementActe extends Model
{
    protected $fillable = ['traitement_id', 'acte', 'base_legale', 'precision'];

    public function traitement(): BelongsTo
    {
        return $this->belongsTo(Traitement::class);
    }
}
