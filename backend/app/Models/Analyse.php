<?php

/**
 * Modele Analyse — Execution du moteur de detection d'ecarts.
 *
 * Une analyse croise les documents d'une mission avec un ou plusieurs
 * referentiels pour produire une liste d'ecarts et un rapport Word.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Analyse extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'mission_id',
        'lancee_par',
        'reference',
        'titre',
        'statut',
        'referentiels_ids',
        'documents_ids',
        'questionnaires_ids',
        'nb_exigences_verifiees',
        'nb_ecarts_critiques',
        'nb_ecarts_majeurs',
        'nb_ecarts_mineurs',
        'score_conformite',
        'synthese',
        'commentaire_ia',
        'rapport_word_path',
        'demarree_a',
        'terminee_a',
        'erreur_message',
        'nb_exigences_total',
        'progression_pct',
        'etape_courante',
        'enrichissement_ia',
        'enrichissement_annule',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'referentiels_ids' => 'array',
            'documents_ids' => 'array',
            'questionnaires_ids' => 'array',
            'synthese' => 'array',
            'demarree_a' => 'datetime',
            'terminee_a' => 'datetime',
            'score_conformite' => 'decimal:2',
            'enrichissement_ia' => 'boolean',
            'enrichissement_annule' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['reference', 'statut', 'score_conformite'])
            ->logOnlyDirty();
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function lanceur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lancee_par');
    }

    public function ecarts(): HasMany
    {
        return $this->hasMany(Ecart::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AnalyseVersion::class)->orderByDesc('numero_version');
    }

    public function referentiels()
    {
        return Referentiel::whereIn('id', $this->referentiels_ids ?? [])->get();
    }

    public function documents()
    {
        return Document::whereIn('id', $this->documents_ids ?? [])->get();
    }

    public function questionnaires()
    {
        return QuestionnaireGenere::whereIn('id', $this->questionnaires_ids ?? [])->get();
    }

    public static function genererReference(): string
    {
        $annee = now()->year;
        $prefix = sprintf('ANA-%d-', $annee);

        // MAX(reference) + 1 sur withTrashed() pour respecter la contrainte
        // unique qui s'applique aussi aux soft-deleted. count() + 1 fait
        // collision des qu'on en supprime une au milieu.
        $dernier = static::withTrashed()
            ->where('reference', 'like', $prefix . '%')
            ->orderByDesc('reference')
            ->value('reference');

        $numero = 1;
        if ($dernier && preg_match('/-(\d+)$/', $dernier, $m)) {
            $numero = ((int) $m[1]) + 1;
        }

        return sprintf('ANA-%d-%03d', $annee, $numero);
    }
}
