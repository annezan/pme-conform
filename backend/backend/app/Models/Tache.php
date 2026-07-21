<?php

/**
 * Modele Tache — Taches confiees aux agents AS Consulting.
 *
 * Sert au pilotage interne (qui fait quoi pour quel client, dans quels
 * delais). Les rôles avec 'taches.view_all' voient tout, les rôles avec
 * uniquement 'taches.view_mine' voient leurs taches assignees.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tache extends Model
{
    use HasFactory;

    protected $table = 'taches';

    protected $fillable = [
        'client_id',
        'mission_id',
        'assignee_id',
        'assignee_par',
        'titre',
        'description',
        'type',
        'priorite',
        'statut',
        'echeance',
        'demarree_a',
        'terminee_a',
        'commentaire_cloture',
    ];

    protected function casts(): array
    {
        return [
            'echeance' => 'date',
            'demarree_a' => 'datetime',
            'terminee_a' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_par');
    }

    public function scopeOuvertes($query)
    {
        return $query->whereIn('statut', ['a_faire', 'en_cours', 'bloquee']);
    }
}
