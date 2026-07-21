<?php

/**
 * Mail AccountValidatedMail — Email envoye au client_admin apres que son
 * compte (cree via /inscription) a ete valide par AS Consulting.
 *
 * Le destinataire peut desormais se connecter a la plateforme.
 */

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountValidatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [new Address($this->user->email, $this->user->nom_complet ?? $this->user->email)],
            subject: 'Votre compte PME-CONFORM a été validé',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account_validated',
            with: [
                'user' => $this->user,
                'loginUrl' => config('app.frontend_url', 'http://localhost:5173') . '/login',
            ],
        );
    }
}
