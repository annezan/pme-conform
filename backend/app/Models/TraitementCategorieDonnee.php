<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TraitementCategorieDonnee extends Model
{
    protected $table = 'traitement_categories_donnees';

    protected $fillable = ['traitement_id', 'categorie_principale', 'detail', 'origine', 'est_sensible'];

    protected function casts(): array
    {
        return ['est_sensible' => 'boolean'];
    }

    public function traitement(): BelongsTo
    {
        return $this->belongsTo(Traitement::class);
    }
}
