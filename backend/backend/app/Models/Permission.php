<?php

/**
 * Modèle Permission — Permission personnalisée pour ASC-IA.
 *
 * Étend le modèle de base de Spatie Permission pour ajouter
 * des fonctionnalités spécifiques au projet.
 */

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends SpatiePermission
{
    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'group',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Rôles qui ont cette permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(config('permission.models.role'), 'role_has_permissions');
    }

    /**
     * Scope pour filtrer les permissions actives.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope pour filtrer par groupe.
     */
    public function scopeByGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    /**
     * Scope pour rechercher par nom ou description.
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'LIKE', "%{$term}%")
              ->orWhere('description', 'LIKE', "%{$term}%");
        });
    }
}
