<?php

/**
 * Modele Ecart — Manquement detecte entre une exigence et un document client.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Ecart extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'analyse_id',
        'referentiel_id',
        'referentiel_chunk_id',
        'document_id',
        'gravite',
        'categorie',
        'type_ecart',
        'titre',
        'exigence_referentiel',
        'article_reference',
        'description_ecart',
        'risque',
        'recommandation',
        'extrait_document',
        'documents_sources',
        'source_fichier',
        'question_numero',
        'question_texte',
        'reponse_client',
        'score_similarite',
        'statut_correction',
        'assigne_a',
        'echeance_correction',
        'notes_consultant',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'score_similarite' => 'decimal:4',
            'echeance_correction' => 'date',
            'metadata' => 'array',
            'documents_sources' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['gravite', 'statut_correction', 'assigne_a'])
            ->logOnlyDirty();
    }

    public function analyse(): BelongsTo
    {
        return $this->belongsTo(Analyse::class);
    }

    public function referentiel(): BelongsTo
    {
        return $this->belongsTo(Referentiel::class);
    }

    public function referentielChunk(): BelongsTo
    {
        return $this->belongsTo(ReferentielChunk::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function assigne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigne_a');
    }

    public function preuves(): HasMany
    {
        return $this->hasMany(EcartPreuve::class)->latest();
    }

    public function scopeOuverts($query)
    {
        return $query->where('statut_correction', 'ouvert');
    }

    public function scopeCritiques($query)
    {
        return $query->where('gravite', 'critique');
    }
}
