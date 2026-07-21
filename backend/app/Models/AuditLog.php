<?php

/**
 * Modèle AuditLog — Journal d'audit de la plateforme.
 *
 * Enregistre chaque action avec horodatage, utilisateur, IP et résultat.
 * Complète Spatie Activity Log pour les besoins de conformité.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public $timestamps = false; // Seul created_at est utilisé

    protected $fillable = [
        'user_id',
        'action',
        'categorie',
        'description',
        'ip_address',
        'user_agent',
        'auditable_type',
        'auditable_id',
        'anciennes_valeurs',
        'nouvelles_valeurs',
        'resultat',
        'message_erreur',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'anciennes_valeurs' => 'array',
            'nouvelles_valeurs' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Crée une entrée d'audit facilement.
     */
    public static function enregistrer(
        string $action,
        ?string $description = null,
        ?Model $auditable = null,
        string $categorie = 'general',
        string $resultat = 'succes',
        ?array $metadata = null,
    ): static {
        $request = request();

        return static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'categorie' => $categorie,
            'description' => $description,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable?->getKey(),
            'resultat' => $resultat,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
