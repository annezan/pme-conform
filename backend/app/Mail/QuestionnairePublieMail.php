<?php

/**
 * Mail QuestionnairePublieMail — Notification envoyee a l'utilisateur du pole
 * concerne lorsqu'AS Consulting publie un questionnaire pour lui.
 *
 * Le destinataire est l'utilisateur de l'entreprise dont le pivot client_user.pole
 * correspond au pole du questionnaire. Si aucun utilisateur n'est rattache au pole,
 * on retombe sur le contact principal du client.
 */

namespace App\Mail;

use App\Models\QuestionnaireGenere;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuestionnairePublieMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public QuestionnaireGenere $questionnaire,
        public User $destinataire,
        public ?string $nomEntreprise = null,
        public ?string $publiePar = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [new Address($this->destinataire->email, trim($this->destinataire->prenom . ' ' . $this->destinataire->nom))],
            subject: "Nouveau formulaire à remplir : {$this->questionnaire->titre}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.questionnaire_publie',
            with: [
                'questionnaire' => $this->questionnaire,
                'destinataire' => $this->destinataire,
                'nomEntreprise' => $this->nomEntreprise,
                'publiePar' => $this->publiePar,
                'lienFormulaire' => rtrim(config('app.frontend_url', 'http://localhost:5173'), '/')
                    . "/questionnaires-generes/{$this->questionnaire->id}",
                'nbQuestions' => count($this->questionnaire->questions ?? []),
            ],
        );
    }
}
