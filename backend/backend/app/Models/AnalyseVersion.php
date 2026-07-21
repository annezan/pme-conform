<?php

/**
 * Modele AnalyseVersion — Snapshot historique d'une analyse.
 *
 * Chaque fois qu'une analyse est relancee via "Refaire l'analyse",
 * l'etat courant est fige dans une ligne analyse_versions :
 *   - score et stats au moment du gel ;
 *   - serialisation des ecarts et preuves ;
 *   - copie du rapport Word.
 *
 * Le numero_version est monotone par analyse, croissant a chaque relance.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyseVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'analyse_id',
        'auteur_id',
        'numero_version',
        'motif',
        'statut',
        'score_conformite',
        'nb_exigences_total',
        'nb_exigences_verifiees',
        'nb_ecarts_critiques',
        'nb_ecarts_majeurs',
        'nb_ecarts_mineurs',
        'referentiels_ids',
        'documents_ids',
        'questionnaires_ids',
        'synthese',
        'ecarts_snapshot',
        'preuves_snapshot',
        'rapport_word_path',
        'commentaire_ia',
        'demarree_a',
        'terminee_a',
    ];

    protected function casts(): array
    {
        return [
            'referentiels_ids' => 'array',
            'documents_ids' => 'array',
            'questionnaires_ids' => 'array',
            'synthese' => 'array',
            'ecarts_snapshot' => 'array',
            'preuves_snapshot' => 'array',
            'score_conformite' => 'decimal:2',
            'demarree_a' => 'datetime',
            'terminee_a' => 'datetime',
        ];
    }

    public function analyse(): BelongsTo
    {
        return $this->belongsTo(Analyse::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
