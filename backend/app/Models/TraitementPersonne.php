<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TraitementPersonne extends Model
{
    protected $fillable = ['traitement_id', 'categorie', 'documentation_source'];

    public function traitement(): BelongsTo
    {
        return $this->belongsTo(Traitement::class);
    }
}
