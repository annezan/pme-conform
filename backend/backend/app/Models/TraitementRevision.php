<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TraitementRevision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'traitement_id',
        'modifie_par',
        'snapshot',
        'commentaire',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function traitement(): BelongsTo
    {
        return $this->belongsTo(Traitement::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifie_par');
    }
}
