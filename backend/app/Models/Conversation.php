<?php

/**
 * Modèle Conversation — Échange entre un utilisateur et un agent IA.
 *
 * Liée optionnellement à une mission pour contextualiser les réponses.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'user_id',
        'agent_id',
        'mission_id',
        'titre',
        'statut',
        'contexte',
    ];

    protected function casts(): array
    {
        return [
            'contexte' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function scopeActives($query)
    {
        return $query->where('statut', 'active');
    }

    /**
     * Retourne l'historique des messages formaté pour le LLM.
     */
    public function getHistoriquePourLlm(int $limite = 20): array
    {
        return $this->messages()
            ->latest()
            ->take($limite)
            ->get()
            ->reverse()
            ->map(fn (Message $m) => [
                'role' => $m->role,
                'content' => $m->contenu,
            ])
            ->values()
            ->toArray();
    }
}
