<?php

/**
 * Modele RegistreKyc — Registre des traitements (PDF/DOCX/XLSX) genere
 * dynamiquement a partir des traitements valides d'un client.
 *
 * Chaque generation est horodatee et empreintee (hash SHA-256 anti-tampering).
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistreKyc extends Model
{
    use HasFactory;

    protected $table = 'registres_kyc';

    protected $fillable = [
        'client_id',
        'genere_par',
        'reference',
        'nb_traitements',
        'snapshot_traitements',
        'fichier_path',
        'hash_fichier',
        'format',
        'statut_generation',
        'erreur_message',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_traitements' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function genereur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'genere_par');
    }

    public static function genererReference(): string
    {
        $annee = now()->year;
        $prefix = \sprintf('REG-%d-', $annee);

        // MAX(reference) + 1 au lieu de count() + 1 pour eviter les collisions
        // d'index unique des qu'on supprime une ligne au milieu.
        $dernier = static::where('reference', 'like', $prefix . '%')
            ->orderByDesc('reference')
            ->value('reference');

        $numero = 1;
        if ($dernier && preg_match('/-(\d+)$/', $dernier, $m)) {
            $numero = ((int) $m[1]) + 1;
        }

        return \sprintf('REG-%d-%03d', $annee, $numero);
    }
}
