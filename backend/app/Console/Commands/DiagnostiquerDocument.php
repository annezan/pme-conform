<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Services\Document\ExtractorFactory;
use Illuminate\Console\Command;

/**
 * Commande de diagnostic de l'extraction d'un document.
 *
 * Le statut "erreur" d'un document ne stocke aucun detail : cette commande
 * rejoue l'extraction en direct et affiche la cause exacte (fichier manquant,
 * type non supporte, exception de l'extracteur, ou texte vide = PDF scanne).
 *
 * Exemples :
 *   php artisan document:diagnostiquer --list        # liste les docs en erreur
 *   php artisan document:diagnostiquer 42            # diagnostique le document 42
 *   php artisan document:diagnostiquer 42 --reprocess # rejoue aussi l'indexation
 */
class DiagnostiquerDocument extends Command
{
    protected $signature = 'document:diagnostiquer
        {id? : ID du document a diagnostiquer}
        {--list : Liste les documents en statut erreur (id, titre, type)}
        {--reprocess : Relance ProcessDocumentJob apres un diagnostic reussi}';

    protected $description = "Diagnostique l'extraction d'un document et affiche la cause exacte d'une erreur.";

    public function handle(ExtractorFactory $extractorFactory): int
    {
        if ($this->option('list')) {
            return $this->listerErreurs();
        }

        $id = $this->argument('id');
        if (! $id) {
            $this->error('Precisez un ID de document, ou utilisez --list pour voir les documents en erreur.');

            return self::FAILURE;
        }

        $document = Document::find($id);
        if (! $document) {
            $this->error("Document introuvable : id = {$id}");

            return self::FAILURE;
        }

        $this->info("Document #{$document->id} : {$document->titre}");
        $this->line("  statut actuel : {$document->statut}");
        $this->line("  type MIME     : {$document->type_mime}");
        $this->line("  nom fichier   : {$document->nom_fichier_original}");

        // 1. Fichier attache present ?
        $media = $document->getFirstMedia('fichiers');
        if (! $media) {
            $this->error('  => CAUSE : aucun fichier attache (media "fichiers" manquant).');

            return self::FAILURE;
        }

        $chemin = $media->getPath();
        $this->line("  chemin        : {$chemin}");
        if (! is_file($chemin)) {
            $this->error('  => CAUSE : le fichier est absent du disque a ce chemin.');

            return self::FAILURE;
        }
        $this->line('  taille disque : ' . number_format(filesize($chemin) / 1024, 1) . ' Ko');

        // 2. Type supporte ?
        if (! $extractorFactory->supporte($document->type_mime)) {
            $this->error("  => CAUSE : type MIME non supporte ({$document->type_mime}).");

            return self::FAILURE;
        }

        // 3. Extraction en direct + capture de l'erreur exacte
        $this->newLine();
        $this->info('Extraction en cours...');
        try {
            $texte = $extractorFactory->extraire($chemin, $document->type_mime);
        } catch (\Throwable $e) {
            $this->error('  => CAUSE : exception pendant l\'extraction');
            $this->error('     ' . get_class($e) . ' : ' . $e->getMessage());
            $this->line('     ' . $e->getFile() . ':' . $e->getLine());

            return self::FAILURE;
        }

        $longueur = mb_strlen(trim($texte));
        if ($longueur === 0) {
            $this->warn('  => CAUSE : extraction OK mais texte VIDE (0 caractere).');
            $this->warn('     Typique d\'un PDF scanne (image sans couche texte) : un OCR serait necessaire.');

            return self::FAILURE;
        }

        $this->info("  => Extraction OK : {$longueur} caracteres.");
        $this->line('  Apercu : ' . str_replace("\n", ' ', mb_substr(trim($texte), 0, 300)) . '...');

        // 4. Reindexation complete en option
        if ($this->option('reprocess')) {
            $this->newLine();
            $this->info('Relance de ProcessDocumentJob (synchrone)...');
            try {
                ProcessDocumentJob::dispatchSync($document);
                $document->refresh();
                $this->info("  => Nouveau statut : {$document->statut}");
            } catch (\Throwable $e) {
                $this->error('  => Echec du job : ' . $e->getMessage());

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function listerErreurs(): int
    {
        $documents = Document::where('statut', 'erreur')
            ->orderBy('id')
            ->get(['id', 'titre', 'type_mime', 'nom_fichier_original']);

        if ($documents->isEmpty()) {
            $this->info('Aucun document en statut erreur.');

            return self::SUCCESS;
        }

        $this->info("{$documents->count()} document(s) en erreur :");
        $this->table(
            ['ID', 'Titre', 'Type MIME', 'Fichier'],
            $documents->map(fn ($d) => [
                $d->id,
                mb_substr($d->titre, 0, 40),
                $d->type_mime,
                mb_substr((string) $d->nom_fichier_original, 0, 30),
            ])->all(),
        );
        $this->newLine();
        $this->line('Diagnostiquez-en un avec : php artisan document:diagnostiquer <ID>');

        return self::SUCCESS;
    }
}
