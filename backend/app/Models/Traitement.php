<?php

/**
 * Modele Traitement — Fiche de traitement modele MOBISOFT.
 *
 * Une fiche regroupe :
 *   - identification (designation, code, direction/pole, dates)
 *   - supports (materiels, logiciels, papier)
 *   - actes + bases legales
 *   - personnes concernees
 *   - categories de donnees collectees (avec sous-detail + origine)
 *   - transferts hors CEDEAO
 *   - mesures de securite par categorie
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Traitement extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'client_id',
        'reference',
        'code_finalite',
        'designation',
        'description',
        'direction_pole',
        'services_charges',
        'sources',
        'contient_donnees_sensibles',
        'transfert_hors_cedeao',
        'date_creation_fiche',
        'date_maj_fiche',
        'statut',
        'saisi_par',
        'valide_par',
        'valide_at',
    ];

    protected function casts(): array
    {
        return [
            'services_charges' => 'array',
            'sources' => 'array',
            'contient_donnees_sensibles' => 'boolean',
            'transfert_hors_cedeao' => 'boolean',
            'date_creation_fiche' => 'date',
            'date_maj_fiche' => 'date',
            'valide_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['designation', 'statut', 'reference'])
            ->logOnlyDirty();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function saisiPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par');
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function supports(): HasMany
    {
        return $this->hasMany(TraitementSupport::class);
    }

    public function actes(): HasMany
    {
        return $this->hasMany(TraitementActe::class);
    }

    public function personnes(): HasMany
    {
        return $this->hasMany(TraitementPersonne::class);
    }

    public function categoriesDonnees(): HasMany
    {
        return $this->hasMany(TraitementCategorieDonnee::class);
    }

    public function transferts(): HasMany
    {
        return $this->hasMany(TraitementTransfert::class);
    }

    public function mesuresSecurite(): HasMany
    {
        return $this->hasMany(TraitementMesureSecurite::class);
    }

    public function scopeValides($query)
    {
        return $query->where('statut', 'valide');
    }

    public function scopePourClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public static function genererReference(int $clientId): string
    {
        $annee = now()->year;
        $prefix = \sprintf('TRT-%d-', $annee);

        // MAX(reference) + 1 sur withTrashed() pour respecter la contrainte
        // unique qui s'applique aussi aux soft-deleted. Scope sur le client
        // pour conserver un compteur par entreprise.
        $dernier = static::withTrashed()
            ->where('client_id', $clientId)
            ->where('reference', 'like', $prefix . '%')
            ->orderByDesc('reference')
            ->value('reference');

        $numero = 1;
        if ($dernier && preg_match('/-(\d+)$/', $dernier, $m)) {
            $numero = ((int) $m[1]) + 1;
        }

        return \sprintf('TRT-%d-%03d', $annee, $numero);
    }
}
