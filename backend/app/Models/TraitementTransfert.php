<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TraitementTransfert extends Model
{
    protected $fillable = ['traitement_id', 'organe', 'pays', 'garantie', 'sens_groupe'];

    public function traitement(): BelongsTo
    {
        return $this->belongsTo(Traitement::class);
    }
}
