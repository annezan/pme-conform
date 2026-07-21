<?php

/**
 * Job AnalyserMissionJob — Execute l'analyse d'ecarts + genere le rapport Word.
 *
 * Async : le controleur cree l'analyse en statut 'en_attente' puis dispatche ce job.
 * Le front suit l'avancement par polling sur /analyses/{id}.
 */

namespace App\Jobs;

use App\Models\Analyse;
use App\Services\Analyse\RapportPptxGenerator;
use App\Services\Analyse\GapAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyserMissionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;

    public function __construct(
        private Analyse $analyse,
    ) {
        $this->onQueue('analyses');
    }

    public function handle(
        GapAnalysisService $gapService,
        RapportPptxGenerator $rapportGenerator,
    ): void {
        // Job execute aussi en `dispatchAfterResponse` (mode rapide sans worker).
        // Sans ca, le max_execution_time PHP (30s par defaut en web) couperait
        // l'execution apres la reponse HTTP.
        @set_time_limit(0);
        @ignore_user_abort(true);

        try {
            $gapService->executer($this->analyse);

            // Genere le rapport .docx seulement si l'analyse s'est bien terminee
            $this->analyse->refresh();
            if ($this->analyse->statut === 'terminee') {
                $rapportGenerator->generer($this->analyse);
            }
        } catch (\Throwable $e) {
            Log::error("AnalyserMissionJob : echec pour analyse {$this->analyse->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
