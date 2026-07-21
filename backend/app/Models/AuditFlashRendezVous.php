<?php

/**
 * Modele AuditFlashRendezVous — Demande de RDV apres audit flash.
 *
 * Soumis par un client a l'issue d'un audit flash pour solliciter :
 *  - un accompagnement AS Consulting
 *  - un audit complet
 *
 * La creation declenche une notification email vers la boite centrale
 * (config services.asc.contact_email) ET les utilisateurs administrateurs.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuditFlashRendezVous extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'audit_flash_rendez_vous';

    protected $fillable = [
        'client_id',
        'mission_id',
        'questionnaire_genere_id',
        'user_id',
        'nom',
        'email',
        'telephone',
        'creneau_souhaite',
        'creneau_libelle',
        'type_demande',
        'message',
        'statut',
        'notes_internes',
        'contacte_at',
        'assigne_a',
    ];

    protected function casts(): array
    {
        return [
            'creneau_souhaite' => 'datetime',
            'contacte_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireGenere::class, 'questionnaire_genere_id');
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigne_a');
    }
}
