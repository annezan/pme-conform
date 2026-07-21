<?php

/**
 * Organigramme — Structure organisationnelle du client (Methode 2,
 * etape 2). Soit fichier uploade par le client, soit JSON arborescent
 * saisi via formulaire :
 *   [{pole: 'Pole RH', services: [{nom: 'Paie', postes: ['Gestionnaire']}]}]
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organigramme extends Model
{
    protected $fillable = [
        'mission_id', 'mode', 'structure',
        'fichier_chemin', 'fichier_mime', 'fichier_nom_original', 'fichier_taille_octets',
        'statut', 'valide_par', 'valide_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'structure' => 'array',
            'metadata' => 'array',
            'valide_at' => 'datetime',
        ];
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function questionnaires(): HasMany
    {
        return $this->hasMany(QuestionnaireGenere::class);
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }
}
