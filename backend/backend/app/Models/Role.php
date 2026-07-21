<?php

/**
 * Modèle Role — Rôle personnalisé pour ASC-IA.
 *
 * Étend le modèle de base de Spatie Role pour ajouter
 * des fonctionnalités spécifiques au projet.
 */

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Role extends SpatieRole
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'is_active',
        'seeded_permissions',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'seeded_permissions' => 'array',
    ];

    /**
     * Permissions associées à ce rôle.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            config('permission.models.permission'), 
            'role_has_permissions',
            'role_id',
            'permission_id'
        );
    }

    /**
     * Utilisateurs qui ont ce rôle.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            config('auth.providers.users.model'),
            'model_has_roles',
            'role_id',
            'model_id'
        );
    }

    /**
     * Scope pour filtrer les rôles actifs.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Relations d'audit
     */
    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function supprimeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Scope pour rechercher par nom ou description.
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%");
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'is_active'])
            ->logOnlyDirty();
    }
}
