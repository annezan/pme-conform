<?php

namespace App\Console\Commands;

use App\Jobs\IndexReferentielJob;
use App\Jobs\ProcessDocumentJob;
use App\Models\Analyse;
use App\Models\Document;
use App\Models\Referentiel;
use App\Services\Analyse\GapAnalysisService;
use Illuminate\Console\Command;

class RejouerAnalyse extends Command
{
    protected $signature = 'analyse:rejouer
        {id : ID de l\'analyse a rejouer}
        {--skip-referentiels : Ne pas reindexer les referentiels}
        {--skip-documents : Ne pas reindexer les documents}
        {--skip-reindex : Sauter toute reindexation (relance uniquement le moteur d\'analyse)}';

    protected $description = 'Reindexe referentiels + documents puis relance l\'analyse d\'ecarts.';

    public function handle(GapAnalysisService $service): int
    {
        $analyse = Analyse::find($this->argument('id'));
        if (! $analyse) {
            $this->error("Analyse #{$this->argument('id')} introuvable.");

            return self::FAILURE;
        }

        $this->info("Rejeu de l'analyse #{$analyse->id} ({$analyse->titre})");

        $skipAll = (bool) $this->option('skip-reindex');

        if (! $skipAll && ! $this->option('skip-referentiels')) {
            $this->reindexerReferentiels($analyse->referentiels_ids ?? []);
        }

        if (! $skipAll && ! $this->option('skip-documents')) {
            $this->reindexerDocuments($analyse->documents_ids ?? []);
        }

        $this->reinitialiserAnalyse($analyse);

        $this->info('Lancement du moteur d\'analyse...');
        $service->executer($analyse->fresh());

        $a = $analyse->fresh();
        $this->newLine();
        $this->info(sprintf(
            'Termine : %d exigences, %d critiques / %d majeurs / %d mineurs, score %s%%',
            $a->nb_exigences_verifiees,
            $a->nb_ecarts_critiques,
            $a->nb_ecarts_majeurs,
            $a->nb_ecarts_mineurs,
            $a->score_conformite
        ));

        return self::SUCCESS;
    }

    private function reindexerReferentiels(array $ids): void
    {
        if (empty($ids)) {
            $this->warn('Aucun referentiel attache a cette analyse.');

            return;
        }

        $referentiels = Referentiel::whereIn('id', $ids)->get();
        $this->info("Reindexation de {$referentiels->count()} referentiel(s)...");
        $bar = $this->output->createProgressBar($referentiels->count());

        foreach ($referentiels as $ref) {
            $ref->chunks()->delete();
            dispatch_sync(new IndexReferentielJob($ref));
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
    }

    private function reindexerDocuments(array $ids): void
    {
        if (empty($ids)) {
            $this->warn('Aucun document attache a cette analyse.');

            return;
        }

        $documents = Document::whereIn('id', $ids)->get();
        $this->info("Reindexation de {$documents->count()} document(s)...");
        $bar = $this->output->createProgressBar($documents->count());

        foreach ($documents as $doc) {
            $doc->chunks()->delete();
            $doc->update([
                'statut' => 'en_attente',
                'is_questionnaire' => false,
                'nb_questions' => 0,
                'nb_questions_repondues' => 0,
                'questions_data' => null,
            ]);
            dispatch_sync(new ProcessDocumentJob($doc));
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
    }

    private function reinitialiserAnalyse(Analyse $analyse): void
    {
        $this->info('Purge des ecarts precedents...');
        $analyse->ecarts()->delete();
        $analyse->update([
            'statut' => 'en_attente',
            'progression_pct' => 0,
            'etape_courante' => null,
            'nb_exigences_verifiees' => 0,
            'nb_ecarts_critiques' => 0,
            'nb_ecarts_majeurs' => 0,
            'nb_ecarts_mineurs' => 0,
            'score_conformite' => null,
            'synthese' => null,
            'commentaire_ia' => null,
            'erreur_message' => null,
            'demarree_a' => null,
            'terminee_a' => null,
        ]);
    }
}
