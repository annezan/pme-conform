<?php

/**
 * Modele PlanActionItem — Action individuelle d'un plan d'action.
 * Peut etre liee a un ecart specifique detecte lors de l'analyse.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanActionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_action_id',
        'ecart_id',
        'position',
        'titre',
        'description',
        'priorite',
        'statut',
        'responsable_id',
        'echeance',
        'termine_le',
        'notes_client',
        'notes_consultant',
        'verdict_correction',
        'justification_correction',
        'verifie_le',
    ];

    protected function casts(): array
    {
        return [
            'echeance' => 'date',
            'termine_le' => 'datetime',
            'verifie_le' => 'datetime',
        ];
    }

    public function preuves(): HasMany
    {
        return $this->hasMany(PlanActionItemPreuve::class)->latest();
    }

    public function planAction(): BelongsTo
    {
        return $this->belongsTo(PlanAction::class);
    }

    public function ecart(): BelongsTo
    {
        return $this->belongsTo(Ecart::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }
}
