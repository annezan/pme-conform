<?php

/**
 * QuestionnaireGenere — Questionnaire produit par l'IA depuis
 * l'organigramme (1 par pole/service detecte). Le client le remplit,
 * les reponses alimentent ensuite l'analyse d'ecarts.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionnaireGenere extends Model
{
    protected $table = 'questionnaires_generes';

    protected $fillable = [
        'mission_id', 'client_id', 'organigramme_id', 'pole', 'service', 'titre', 'description',
        'questions', 'source', 'themes', 'statut', 'reponses',
        'est_publie', 'publie_le', 'publie_par',
        'genere_par', 'rempli_par', 'envoye_a', 'rempli_a',
    ];

    protected function casts(): array
    {
        return [
            'questions' => 'array',
            'themes' => 'array',
            'reponses' => 'array',
            'envoye_a' => 'datetime',
            'rempli_a' => 'datetime',
            'est_publie' => 'boolean',
            'publie_le' => 'datetime',
        ];
    }

    /**
     * Scope : uniquement les questionnaires publies (visibles cote client).
     */
    public function scopePublies($query)
    {
        return $query->where('est_publie', true);
    }

    public function publieur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publie_par');
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function organigramme(): BelongsTo
    {
        return $this->belongsTo(Organigramme::class);
    }

    public function genereur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'genere_par');
    }

    public function repondeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rempli_par');
    }
}
