<?php

/**
 * Modèle User — Utilisateur de la plateforme ASC-IA.
 *
 * Rôles possibles via Spatie Permission : admin, manager, consultant, client.
 * Relié aux clients via table pivot, aux conversations et aux missions.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, LogsActivity, Notifiable, SoftDeletes;

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'password',
        'telephone',
        'poste',
        'is_active',
        'compte_valide',
        'valide_le',
        'valide_par',
        'must_change_password',
        'mdp_temporaire_expire_le',
        'derniere_connexion',
        'role_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'derniere_connexion' => 'datetime',
            'valide_le' => 'datetime',
            'mdp_temporaire_expire_le' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'compte_valide' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Utilisateur ASC qui a valide ce compte (nullable, renseigne uniquement
     * pour les comptes crees via /inscription et valides ulterieurement).
     */
    public function valideur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nom', 'prenom', 'email', 'poste', 'is_active'])
            ->logOnlyDirty();
    }

    // Nom complet
    public function getNomCompletAttribute(): string
    {
        return "{$this->prenom} {$this->nom}";
    }

    // Rôle de l'utilisateur
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    // Clients assignés à cet utilisateur
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class)
            ->withPivot('pole')
            ->withTimestamps();
    }

    // Missions dont l'utilisateur est responsable principal (retro-compat).
    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class, 'responsable_id');
    }

    // Missions affectees a l'utilisateur via la pivot mission_user
    // (createur, responsable, consultant additionnel). Utilise pour le
    // scoping : un consultant voit les missions dont il fait partie.
    public function missionsAffectees(): BelongsToMany
    {
        return $this->belongsToMany(Mission::class, 'mission_user')
            ->withPivot(['role_dans_mission', 'affecte_le', 'affecte_par'])
            ->withTimestamps();
    }

    // Vérifier si l'utilisateur a un rôle spécifique
    public function hasRole(string|array $role): bool
    {
        if (is_array($role)) {
            return $this->hasAnyRole($role);
        }
        return $this->role && $this->role->name === $role;
    }

    // Vérifier si l'utilisateur a l'un des rôles spécifiés
    public function hasAnyRole(array $roles): bool
    {
        return $this->role && in_array($this->role->name, $roles);
    }

    /**
     * Verifie si l'utilisateur (via son role) detient une permission donnee.
     *
     * Le modele d'authz est hybride : Spatie Role + Permission cote pivot
     * `role_has_permissions`, mais l'utilisateur est rattache a un role par
     * cle etrangere `users.role_id` (pas via `model_has_roles`).
     *
     * Regles :
     *  - admin : super-user, retourne toujours true (intent du seeder)
     *  - permission exacte : match direct
     *  - umbrella : `view-foo` est implicitement couvert par `manage-foo`
     */
    public function hasPermissionTo(string $permission, ?string $guardName = null): bool
    {
        if (! $this->role) {
            return false;
        }

        if ($this->role->name === 'admin') {
            return true;
        }

        $names = $this->role->permissions()->pluck('name');

        if ($names->contains($permission)) {
            return true;
        }

        if (preg_match('/^(view|create|update|delete|accept|close|sign|revoke|download|generate|regenerate|upload|input|validate|archive|submit|freeze|reindex|enrich|cancel|restart)-(.+)$/', $permission, $m)) {
            return $names->contains('manage-' . $m[2]);
        }

        return false;
    }

    /**
     * Verifie si l'utilisateur detient au moins une des permissions listees.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermissionTo($permission)) {
                return true;
            }
        }
        return false;
    }

    // Conversations de l'utilisateur
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    // Documents uploadés par l'utilisateur
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'uploaded_by');
    }

    // Traitements saisis par cet utilisateur (PME-CONFORM)
    public function traitementsSaisis(): HasMany
    {
        return $this->hasMany(Traitement::class, 'saisi_par');
    }

    // Signatures de chartes effectuees par cet utilisateur
    public function signaturesCharte(): HasMany
    {
        return $this->hasMany(Signature::class);
    }

    /**
     * Retourne le Client principal de l'utilisateur (pour les roles client/client_admin).
     * Shortcut pour $user->clients()->first().
     */
    public function clientPrincipal(): ?Client
    {
        return $this->clients()->first();
    }
}
