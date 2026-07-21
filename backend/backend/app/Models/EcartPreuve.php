<?php

/**
 * Modele EcartPreuve — Piece justificative attachee a un ecart de conformite.
 *
 * Une preuve documente la correction d'un ecart (procedure mise en place,
 * contrat signe, capture d'ecran, attestation, etc.). Stockee sur le disque
 * local sous storage/app/ecarts/{ecart_id}/.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EcartPreuve extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ecart_id',
        'uploaded_by',
        'libelle',
        'description',
        'nom_fichier_original',
        'chemin',
        'mime',
        'taille_octets',
    ];

    protected function casts(): array
    {
        return [
            'taille_octets' => 'integer',
        ];
    }

    public function ecart(): BelongsTo
    {
        return $this->belongsTo(Ecart::class);
    }

    public function uploadeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
