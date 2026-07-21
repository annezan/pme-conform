<?php

/**
 * Modele Referentiel — Corpus legal/reglementaire de reference.
 *
 * Global a la plateforme. Sert de base pour detecter les ecarts
 * dans les documents clients lors des analyses de conformite.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Referentiel extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $fillable = [
        'code',
        'titre',
        'description',
        'autorite',
        'version',
        'date_publication',
        'date_entree_vigueur',
        'type',
        'secteurs_activite',
        'statut',
        'contenu_extrait',
        'source_url',
        'created_by',
        'updated_by',
        'deleted_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'date_publication' => 'date',
            'date_entree_vigueur' => 'date',
            'metadata' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'titre', 'version', 'statut'])
            ->logOnlyDirty();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('fichiers')->singleFile();
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function supprimeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function uploadeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(ReferentielChunk::class);
    }

    public function ecarts(): HasMany
    {
        return $this->hasMany(Ecart::class);
    }

    public function scopeActif($query)
    {
        return $query->where('statut', 'actif');
    }

    // Secteurs d'activité couverts par ce référentiel
    public function secteursActivite(): BelongsToMany
    {
        return $this->belongsToMany(SecteurActivite::class, 'referentiel_secteur_activite')
            ->withTimestamps();
    }

    public function scopePourSecteur($query, ?string $secteur)
    {
        if (! $secteur) {
            return $query;
        }

        return $query->whereHas('secteursActivite', function ($subQuery) use ($secteur) {
            $subQuery->where('nom', $secteur);
        });
    }

    
    /**
     * Synchronise les secteurs d'activité à partir d'un tableau d'IDs.
     */
    public function synchroniserSecteurs(array $secteursIds): void
    {
        $this->secteursActivite()->sync($secteursIds);
    }
}
