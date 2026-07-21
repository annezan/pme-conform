<?php

/**
 * Modèle Module — Module métier dynamique de la plateforme.
 *
 * Chaque module est un Service Provider Laravel indépendant.
 * L'orchestrateur détecte les modules actifs au démarrage.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    protected $fillable = [
        'slug',
        'nom',
        'description',
        'version',
        'icone',
        'couleur',
        'service_provider',
        'namespace',
        'chemin',
        'is_active',
        'is_core',
        'configuration',
        'dependances',
        'ordre_affichage',
        'active_depuis',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_core' => 'boolean',
            'configuration' => 'array',
            'dependances' => 'array',
            'active_depuis' => 'datetime',
        ];
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    /**
     * Retourne uniquement les modules activés.
     */
    public function scopeActifs($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Retourne les modules du noyau.
     */
    public function scopeCore($query)
    {
        return $query->where('is_core', true);
    }
}
