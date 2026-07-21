<?php

/**
 * MatriceCollecteEnvoyee — Email envoye au client a la creation de mission
 * Methode 2. Joint la matrice de collecte initiale et indique l'URL de
 * l'espace client pour repondre en ligne ou uploader le fichier rempli.
 */

namespace App\Mail;

use App\Models\Mission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MatriceCollecteEnvoyee extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Mission $mission,
        public ?string $cheminMatricePj = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'PME-CONFORM - Matrice de collecte initiale - Mission ' . $this->mission->reference,
        );
    }

    public function content(): Content
    {
        $urlEspaceClient = config('app.url_frontend', config('app.url'))
            . '/missions/' . $this->mission->id . '/matrice';

        return new Content(
            view: 'emails.matrice_collecte',
            with: [
                'mission' => $this->mission,
                'client' => $this->mission->client,
                'urlEspaceClient' => $urlEspaceClient,
            ],
        );
    }

    public function attachments(): array
    {
        if (! $this->cheminMatricePj || ! file_exists($this->cheminMatricePj)) {
            return [];
        }

        return [
            \Illuminate\Mail\Mailables\Attachment::fromPath($this->cheminMatricePj)
                ->as('Matrice-de-collecte-initiale.docx'),
        ];
    }
}
