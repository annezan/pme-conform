<?php

/**
 * Modele Charte — Document publiable (IA, sous-traitance, CGU...) qu'un
 * client doit signer pour attester de son engagement.
 *
 * Le contenu est immuable par version (hash_contenu verifiable).
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Charte extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'type',
        'titre',
        'version',
        'contenu_html',
        'hash_contenu',
        'active',
        'obligatoire',
        'publiee_le',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'obligatoire' => 'boolean',
            'publiee_le' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['type', 'version', 'active'])
            ->logOnlyDirty();
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(Signature::class);
    }

    public function scopeActives($query)
    {
        return $query->where('active', true);
    }

    /**
     * Calcule le hash SHA-256 du contenu HTML. A appeler a la creation/mise a jour.
     */
    public static function calculerHash(string $contenuHtml): string
    {
        return hash('sha256', trim($contenuHtml));
    }
}
