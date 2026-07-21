<?php

/**
 * Modele Signature — Tracabilite immuable de la signature d'une charte.
 *
 * Chaque signature est liee au hash du contenu affiche au moment de la signature
 * pour garantir l'integrite : si le hash d'une charte change, les signatures
 * existantes restent attachees a leur hash d'origine.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Signature extends Model
{
    use HasFactory;

    protected $fillable = [
        'charte_id',
        'user_id',
        'client_id',
        'hash_contenu_signe',
        'ip_signature',
        'user_agent_signature',
        'statut',
        'signee_le',
        'revoquee_le',
        'raison_revocation',
    ];

    protected function casts(): array
    {
        return [
            'signee_le' => 'datetime',
            'revoquee_le' => 'datetime',
        ];
    }

    public function charte(): BelongsTo
    {
        return $this->belongsTo(Charte::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function scopeActives($query)
    {
        return $query->where('statut', 'signee');
    }

    /**
     * Verifie si la signature est encore valide (contenu de la charte n'a pas change).
     */
    public function estValide(): bool
    {
        return $this->statut === 'signee'
            && $this->hash_contenu_signe === $this->charte?->hash_contenu;
    }
}
