<?php

/**
 * RegenererQuestionnaireJob — Re-prompt LLM pour UN seul questionnaire et
 * remplace ses questions sur place. Asynchrone car le LLM 3B sur CPU peut
 * mettre 30-90s, ce qui depasserait le timeout HTTP cote front.
 *
 * Progression stockee en cache sous "regen:questionnaire:{id}" avec une
 * structure { etat: 'en_file'|'en_cours'|'termine'|'erreur', message? }.
 * TTL 1h : largement de quoi laisser le front polling.
 */

namespace App\Jobs;

use App\Models\QuestionnaireGenere;
use App\Services\Methode2\GenerateurQuestionnaireIA;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RegenererQuestionnaireJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1200;

    public function __construct(
        private int $questionnaireId,
    ) {
        $this->onQueue('questionnaires');
    }

    public static function cleEtat(int $questionnaireId): string
    {
        return "regen:questionnaire:{$questionnaireId}";
    }

    public function handle(GenerateurQuestionnaireIA $generateur): void
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        $cle = self::cleEtat($this->questionnaireId);

        $questionnaire = QuestionnaireGenere::find($this->questionnaireId);
        if (! $questionnaire) {
            Cache::put($cle, ['etat' => 'erreur', 'message' => 'Questionnaire introuvable.'], now()->addHour());

            return;
        }

        // Garde-fou supplementaire cote job : si le questionnaire a ete publie
        // entre le moment du dispatch et l'execution, on annule.
        if ($questionnaire->est_publie) {
            Cache::put($cle, ['etat' => 'erreur', 'message' => 'Questionnaire deja publie : regeneration annulee.'], now()->addHour());

            return;
        }

        Cache::put($cle, ['etat' => 'en_cours', 'debut_at' => now()->toIso8601String()], now()->addHour());

        try {
            $generateur->regenererUn($questionnaire);
            Cache::put($cle, [
                'etat' => 'termine',
                'fin_at' => now()->toIso8601String(),
                'source' => $questionnaire->fresh()->source,
                'nb_questions' => count($questionnaire->fresh()->questions ?? []),
            ], now()->addHour());
        } catch (\Throwable $e) {
            Cache::put($cle, [
                'etat' => 'erreur',
                'fin_at' => now()->toIso8601String(),
                'message' => $e->getMessage(),
            ], now()->addHour());
            Log::error("RegenererQuestionnaireJob #{$this->questionnaireId} : {$e->getMessage()}");
            throw $e;
        }
    }
}
