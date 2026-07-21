<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TraitementSupport extends Model
{
    protected $fillable = ['traitement_id', 'categorie', 'type', 'marque_version', 'precision'];

    public function traitement(): BelongsTo
    {
        return $this->belongsTo(Traitement::class);
    }
}
