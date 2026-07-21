<?php

/**
 * Modele PlanAction — Plan propose par le consultant ASC au client
 * suite a une analyse d'ecarts. Le client l'accepte puis execute les items.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PlanAction extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'plans_actions';

    protected $fillable = [
        'client_id',
        'analyse_id',
        'reference',
        'titre',
        'objectif',
        'propose_par',
        'statut',
        'date_debut_prevue',
        'date_fin_prevue',
        'accepte_le',
        'accepte_par',
        'cloture_le',
        'commentaire_cloture',
        'soumis_le',
        'soumis_par',
        'verification_statut',
        'verification_progression_pct',
    ];

    protected function casts(): array
    {
        return [
            'date_debut_prevue' => 'date',
            'date_fin_prevue' => 'date',
            'accepte_le' => 'datetime',
            'cloture_le' => 'datetime',
            'soumis_le' => 'datetime',
            'verification_progression_pct' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['reference', 'statut', 'titre'])
            ->logOnlyDirty();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function analyse(): BelongsTo
    {
        return $this->belongsTo(Analyse::class);
    }

    public function proposeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'propose_par');
    }

    public function accepteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepte_par');
    }

    public function soumetteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'soumis_par');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PlanActionItem::class)->orderBy('position');
    }

    public static function genererReference(): string
    {
        $annee = now()->year;
        $prefix = \sprintf('PA-%d-', $annee);

        // On parcourt TOUTES les references existantes (y compris soft-deleted)
        // car la contrainte unique plans_actions_reference_unique s'applique
        // aussi a celles-ci. Prendre count() sur les non-supprimees fait
        // collision des qu'on en supprime une au milieu.
        $dernier = static::withTrashed()
            ->where('reference', 'like', $prefix . '%')
            ->orderByDesc('reference')
            ->value('reference');

        $numero = 1;
        if ($dernier && preg_match('/-(\d+)$/', $dernier, $m)) {
            $numero = ((int) $m[1]) + 1;
        }

        return \sprintf('PA-%d-%03d', $annee, $numero);
    }

    /**
     * Calcule la progression (% d'items termines).
     */
    public function progression(): int
    {
        $total = $this->items()->count();
        if ($total === 0) {
            return 0;
        }

        $termines = $this->items()->where('statut', 'termine')->count();

        return (int) round(($termines / $total) * 100);
    }
}
