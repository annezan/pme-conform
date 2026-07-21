<?php

/**
 * Modèle Mission — Dossier de conformité pour un client.
 *
 * Unité de travail principale. Tous les documents, conversations
 * et analyses sont rattachés à une mission.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Mission extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'client_id',
        'responsable_id',
        'reference',
        'titre',
        'description',
        'type',
        'methode',
        'statut',
        'priorite',
        'date_debut',
        'date_echeance',
        'date_cloture',
        'progression',
        'notes_internes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_echeance' => 'date',
            'date_cloture' => 'date',
            'progression' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['titre', 'statut', 'progression'])
            ->logOnlyDirty();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Consultants et managers affectes a la mission (many-to-many).
     * La pivot mission_user contient tous les users qui ont acces a la
     * mission au titre de leur affectation individuelle (le createur, le
     * responsable, et les consultants ajoutes explicitement).
     */
    public function consultants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'mission_user')
            ->withPivot(['role_dans_mission', 'affecte_le', 'affecte_par'])
            ->withTimestamps();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
        
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(Analyse::class);
    }

    public function ecarts()
    {
        return Ecart::whereIn('analyse_id', $this->analyses()->pluck('id'));
    }

    public function matriceCollecte()
    {
        return $this->hasOne(MatriceCollecte::class);
    }

    public function organigramme()
    {
        return $this->hasOne(Organigramme::class);
    }

    public function questionnairesGeneres(): HasMany
    {
        return $this->hasMany(QuestionnaireGenere::class);
    }

    /**
     * Génère automatiquement une référence unique pour la mission.
     */
    public static function genererReference(): string
    {
        $annee = now()->year;
        $prefix = sprintf('MISS-%d-', $annee);

        // On parcourt TOUTES les references existantes (y compris soft-deleted)
        // car la contrainte unique missions_reference_unique s'applique aussi a
        // celles-ci. Prendre count() sur les missions non-supprimees fait
        // collision des qu'on en supprime une au milieu.
        $dernier = static::withTrashed()
            ->where('reference', 'like', $prefix . '%')
            ->orderByDesc('reference')
            ->value('reference');

        $numero = 1;
        if ($dernier && preg_match('/-(\d+)$/', $dernier, $m)) {
            $numero = ((int) $m[1]) + 1;
        }

        return sprintf('MISS-%d-%03d', $annee, $numero);
    }
}
