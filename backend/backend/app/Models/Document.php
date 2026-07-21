<?php

/**
 * Modèle Document — Document uploadé ou généré sur la plateforme.
 *
 * Utilise Spatie Media Library pour la gestion physique des fichiers.
 * Le contenu textuel est extrait et indexé pour le RAG.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Document extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $fillable = [
        'mission_id',
        'client_id',
        'uploaded_by',
        'titre',
        'description',
        'nom_fichier_original',
        'type_mime',
        'taille_octets',
        'type',
        'statut',
        'is_confidentiel',
        'contenu_extrait',
        'hash_fichier',
        'metadata',
        'is_questionnaire',
        'nb_questions',
        'nb_questions_repondues',
        'questions_data',
    ];

    protected function casts(): array
    {
        return [
            'is_confidentiel' => 'boolean',
            'metadata' => 'array',
            'taille_octets' => 'integer',
            'is_questionnaire' => 'boolean',
            'nb_questions' => 'integer',
            'nb_questions_repondues' => 'integer',
            'questions_data' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['titre', 'statut', 'type'])
            ->logOnlyDirty();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('fichiers')
            ->singleFile();
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function uploadeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    public function scopeIndexes($query)
    {
        return $query->where('statut', 'indexe');
    }

    /**
     * Taille formatée pour l'affichage.
     */
    public function getTailleFormateeAttribute(): string
    {
        $octets = $this->taille_octets;
        $unites = ['o', 'Ko', 'Mo', 'Go'];
        $i = 0;

        while ($octets >= 1024 && $i < count($unites) - 1) {
            $octets /= 1024;
            $i++;
        }

        return round($octets, 2) . ' ' . $unites[$i];
    }
}
