<?php

/**
 * Modèle Message — Message dans une conversation IA.
 *
 * Stocke le contenu original et la version pseudonymisée
 * envoyée au LLM, ainsi que les métriques de performance.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'role',
        'contenu',
        'contenu_pseudonymise',
        'metadata',
        'sources',
        'tokens_entree',
        'tokens_sortie',
        'duree_ms',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'sources' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
