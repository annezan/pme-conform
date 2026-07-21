<?php

/**
 * Mail NewUserCredentialsMail — Email envoye a un utilisateur lorsque son
 * compte est cree par un tiers (admin ou client_admin).
 *
 * Contient ses identifiants de connexion ET un mot de passe temporaire en
 * clair. Le destinataire est OBLIGE de le changer a la premiere connexion
 * (flag must_change_password = true cote serveur).
 */

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewUserCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $motDePasseTemporaire,
        public ?string $nomEntreprise = null,
        public ?string $createPar = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [new Address($this->user->email, trim($this->user->prenom . ' ' . $this->user->nom))],
            subject: 'Vos identifiants de connexion à PME-CONFORM',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new_user_credentials',
            with: [
                'user' => $this->user,
                'motDePasseTemporaire' => $this->motDePasseTemporaire,
                'nomEntreprise' => $this->nomEntreprise,
                'createPar' => $this->createPar,
                'loginUrl' => config('app.frontend_url', 'http://localhost:5173') . '/login',
                'expiration' => $this->user->mdp_temporaire_expire_le
                    ? $this->user->mdp_temporaire_expire_le->format('d/m/Y H:i')
                    : null,
            ],
        );
    }
}
