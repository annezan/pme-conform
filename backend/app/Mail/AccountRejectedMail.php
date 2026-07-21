<?php

/**
 * Mail AccountRejectedMail — Email envoye au client_admin apres que sa
 * demande d'inscription a ete refusee par AS Consulting.
 *
 * Le motif du refus (fourni par l'admin) est obligatoire et affiche
 * clairement dans le corps de l'email pour que le destinataire comprenne
 * la decision et sache si une action de sa part est possible.
 */

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $motif,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [new Address($this->user->email, $this->user->nom_complet ?? $this->user->email)],
            subject: 'Votre demande d\'inscription à PME-CONFORM n\'a pas été validée',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account_rejected',
            with: [
                'user' => $this->user,
                'motif' => $this->motif,
                'contactEmail' => config('mail.from.address', 'support@ascacademy.ci'),
            ],
        );
    }
}
