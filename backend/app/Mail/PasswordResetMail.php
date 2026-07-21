<?php

/**
 * Mail PasswordResetMail — Email envoye apres une demande de reinitialisation
 * de mot de passe (POST /api/forgot-password).
 *
 * Contient un lien securise vers /reset-password/{token}?email={email} sur le
 * frontend. Le token est valide pendant config('auth.passwords.users.expire')
 * minutes (par defaut 60).
 */

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [new Address($this->user->email, trim($this->user->prenom . ' ' . $this->user->nom))],
            subject: 'Réinitialisation de votre mot de passe PME-CONFORM',
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
        $resetUrl = "{$frontendUrl}/reset-password/{$this->token}?email=" . urlencode($this->user->email);
        $expirationMinutes = (int) config('auth.passwords.users.expire', 60);

        return new Content(
            view: 'emails.password_reset',
            with: [
                'user' => $this->user,
                'resetUrl' => $resetUrl,
                'expirationMinutes' => $expirationMinutes,
            ],
        );
    }
}
