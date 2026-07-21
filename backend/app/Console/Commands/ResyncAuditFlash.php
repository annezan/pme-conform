<?php

namespace App\Console\Commands;

use App\Models\QuestionnaireGenere;
use App\Services\Methode3\AuditFlashTemplate;
use Illuminate\Console\Command;

class ResyncAuditFlash extends Command
{
    protected $signature = 'audit-flash:resync
        {--id= : ID specifique d\'un QuestionnaireGenere a resynchroniser}';

    protected $description = 'Resynchronise les questionnaires Audit Flash existants avec la derniere version du template (titre + description + 10 questions).';

    public function handle(): int
    {
        $query = QuestionnaireGenere::query()->where('pole', 'Audit Flash');
        if ($id = $this->option('id')) {
            $query->where('id', $id);
        }

        $items = $query->get();
        if ($items->isEmpty()) {
            $this->warn('Aucun questionnaire Audit Flash trouve.');

            return self::SUCCESS;
        }

        $this->info("Resynchronisation de {$items->count()} questionnaire(s)...");
        $bar = $this->output->createProgressBar($items->count());

        foreach ($items as $q) {
            // Preserve les reponses existantes : on ne touche qu'au texte
            // (titre, description, libelles des questions). Les numeros sont
            // conserves a l'identique pour que les reponses restent valides.
            $q->update([
                'titre' => 'Audit Flash — Scan Pénal du Dirigeant',
                'description' => AuditFlashTemplate::description(),
                'questions' => AuditFlashTemplate::questions(),
            ]);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info("Termine : {$items->count()} questionnaire(s) mis a jour avec les textes accentues.");

        return self::SUCCESS;
    }
}
