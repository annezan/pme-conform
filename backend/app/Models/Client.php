<?php

/**
 * Modèle Client — Entreprise cliente accompagnée par AS Consulting.
 *
 * Un client peut avoir plusieurs missions de conformité
 * et être suivi par plusieurs consultants.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Client extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'raison_sociale',
        'sigle',
        'description_activite',
        'numero_registre_commerce',
        'adresse',
        'ville',
        'pays',
        'telephone',
        'email',
        'site_web',
        'contact_principal_nom',
        'contact_principal_email',
        'contact_principal_telephone',
        'contact_principal_poste',
        'statut',
        'notes',
        // Champs etendus PME-CONFORM
        'type_structure',
        'numero_rccm',
        'numero_cc',
        'effectif',
        'chiffre_affaires_mfcfa',
        'date_creation_entreprise',
        'logo_path',
        'onboarding_complete_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'date_creation_entreprise' => 'date',
            'onboarding_complete_at' => 'datetime',
            'chiffre_affaires_mfcfa' => 'decimal:2',
            'effectif' => 'integer',
                    ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['raison_sociale', 'statut'])
            ->logOnlyDirty();
    }

    // Consultants assignés à ce client (ou employés du client, avec leur pôle/service)
    public function utilisateurs(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('pole', 'service')
            ->withTimestamps();
    }

    // Missions de ce client
    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class);
    }

    // Traitements de donnees (PME-CONFORM)
    public function traitements(): HasMany
    {
        return $this->hasMany(Traitement::class);
    }

    // Signatures de chartes par les users de ce client
    public function signatures(): HasMany
    {
        return $this->hasMany(Signature::class);
    }

    // Plans d'actions proposes a ce client
    public function plansActions(): HasMany
    {
        return $this->hasMany(PlanAction::class);
    }

    // Registres KYC generes pour ce client
    public function registresKyc(): HasMany
    {
        return $this->hasMany(RegistreKyc::class);
    }

    
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

    // Informations generales pour le registre MOBISOFT (responsable + DPO)
    public function organisme(): HasOne
    {
        return $this->hasOne(ClientOrganisme::class);
    }

    // Secteurs d'activité du client
    public function secteursActivite(): BelongsToMany
    {
        return $this->belongsToMany(SecteurActivite::class, 'client_secteur_activite')
            ->withTimestamps();
    }

    /**
     * Accesseur pour obtenir les noms des secteurs d'activité
     * pour la compatibilité avec le code existant.
     */
    public function getSecteursActiviteNomsAttribute(): array
    {
        return $this->secteursActivite->pluck('nom')->toArray();
    }

    /**
     * Synchronise les secteurs d'activité à partir d'un tableau d'IDs.
     */
    public function synchroniserSecteurs(array $idsSecteurs): void
    {
        $this->secteursActivite()->sync($idsSecteurs);
    }
}
