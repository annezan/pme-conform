<?php

/**
 * AuditFlashRendezVousDemande — Email envoye a AS Consulting suite a une
 * demande de rendez-vous emise depuis le resultat d'un audit flash.
 *
 * Destinataires : la boite de contact centrale (config services.asc.contact_email)
 * ET les utilisateurs administrateurs.
 */

namespace App\Mail;

use App\Models\AuditFlashRendezVous;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AuditFlashRendezVousDemande extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AuditFlashRendezVous $rendezVous,
    ) {}

    public function envelope(): Envelope
    {
        $type = $this->rendezVous->type_demande === 'audit_complet'
            ? 'Audit complet'
            : 'Accompagnement';

        return new Envelope(
            subject: "PME-CONFORM - Nouvelle demande de {$type} - {$this->rendezVous->nom}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.audit_flash_rendez_vous',
            with: [
                'rdv' => $this->rendezVous,
            ],
        );
    }
}
