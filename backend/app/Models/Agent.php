<?php

/**
 * Modèle Agent — Agent IA de la plateforme.
 *
 * Chaque agent a un prompt système, un type et une configuration.
 * Les agents peuvent être transversaux (noyau) ou liés à un module.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'slug',
        'nom',
        'description',
        'prompt_systeme',
        'icone',
        'couleur',
        'type',
        'is_active',
        'is_core',
        'modele_llm',
        'max_tokens',
        'temperature',
        'configuration',
        'permissions_requises',
        'ordre_affichage',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_core' => 'boolean',
            'temperature' => 'float',
            'configuration' => 'array',
            'permissions_requises' => 'array',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function scopeActifs($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCore($query)
    {
        return $query->where('is_core', true);
    }

    /**
     * Retourne le modèle LLM à utiliser (surcharge ou défaut).
     */
    public function getModeleLlmEffectifAttribute(): string
    {
        return $this->modele_llm ?? config('services.ollama.model', 'llama3.2');
    }

    /**
     * Retourne le max tokens à utiliser (surcharge ou défaut).
     */
    public function getMaxTokensEffectifAttribute(): int
    {
        return $this->max_tokens ?? (int) config('services.ollama.max_tokens', 4096);
    }
}
