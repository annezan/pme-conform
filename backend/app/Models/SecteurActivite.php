<?php

/**
 * Modèle SecteurActivite — Secteur d'activité normalisé.
 *
 * Permet une gestion centralisée des secteurs d'activité
 * utilisés dans les clients et les référentiels.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SecteurActivite extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'secteurs_activite';

    protected $fillable = [
        'nom',
        'description',
        'code',
        'is_actif',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_actif' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nom', 'description', 'code', 'is_actif'])
            ->logOnlyDirty();
    }

    /**
     * Clients qui appartiennent à ce secteur d'activité.
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_secteur_activite')
            ->withTimestamps();
    }

    /**
     * Référentiels qui s'appliquent à ce secteur d'activité.
     */
    public function referentiels(): BelongsToMany
    {
        return $this->belongsToMany(Referentiel::class, 'referentiel_secteur_activite')
            ->withTimestamps();
    }

    /**
     * Scope pour les secteurs actifs.
     */
    public function scopeActif($query)
    {
        return $query->where('is_actif', true);
    }

    /**
     * Scope pour les secteurs inactifs.
     */
    public function scopeInactif($query)
    {
        return $query->where('is_actif', false);
    }

    /**
     * Scope pour rechercher par nom.
     */
    public function scopeRecherche($query, string $terme)
    {
        return $query->where('nom', 'LIKE', "%{$terme}%");
    }

    /**
     * Utilisateur qui a créé ce secteur d'activité.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Utilisateur qui a modifié ce secteur d'activité.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Utilisateur qui a supprimé ce secteur d'activité.
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
