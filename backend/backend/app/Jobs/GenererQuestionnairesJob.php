<?php

/**
 * GenererQuestionnairesJob — Generation asynchrone des questionnaires
 * d'audit a partir d'un organigramme fige.
 *
 * Le `figer` de l'organigramme dispatche ce job au lieu de generer
 * synchroniquement. Indispensable car llama3.2:3b sur CPU peut prendre
 * 30-90s par questionnaire — un organigramme de 5 poles × 3 services
 * occuperait l'HTTP request plusieurs minutes et finirait en 504.
 *
 * Le job ecrit son progres dans organigrammes.metadata.generation pour
 * etre lu par le front (polling) :
 *   { etat: 'en_cours'|'termine'|'erreur', total, faits, debut_at, fin_at }
 */

namespace App\Jobs;

use App\Models\Mission;
use App\Models\Organigramme;
use App\Models\User;
use App\Services\Methode2\GenerateurQuestionnaireIA;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenererQuestionnairesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;          // pas de retry : on appelle un LLM qui peut prendre 5+ minutes
    public int $timeout = 1800;     // 30 min : largement de quoi traiter un organigramme complet

    public function __construct(
        private int $missionId,
        private int $organigrammeId,
        private int $initiateurId,
    ) {
        $this->onQueue('questionnaires');
    }

    public function handle(GenerateurQuestionnaireIA $generateur): void
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        $mission = Mission::find($this->missionId);
        $organigramme = Organigramme::find($this->organigrammeId);
        $initiateur = User::find($this->initiateurId);

        if (! $mission || ! $organigramme || ! $initiateur) {
            Log::error('GenererQuestionnairesJob : mission/organigramme/user introuvable', [
                'mission_id' => $this->missionId,
                'organigramme_id' => $this->organigrammeId,
                'initiateur_id' => $this->initiateurId,
            ]);

            return;
        }

        $this->ecrireProgres($organigramme, [
            'etat' => 'en_cours',
            'debut_at' => now()->toIso8601String(),
            'faits' => 0,
            'total' => $this->compterCibles($organigramme),
        ]);

        try {
            $crees = $generateur->genererDepuisOrganigramme($mission, $organigramme, $initiateur);

            $this->ecrireProgres($organigramme, [
                'etat' => 'termine',
                'fin_at' => now()->toIso8601String(),
                'faits' => count($crees),
                'questionnaires_ids' => array_map(fn ($q) => $q->id, $crees),
            ]);

            Log::info("GenererQuestionnairesJob : " . count($crees) . " questionnaires generes pour mission #{$mission->id}.");
        } catch (\Throwable $e) {
            $this->ecrireProgres($organigramme, [
                'etat' => 'erreur',
                'fin_at' => now()->toIso8601String(),
                'message' => $e->getMessage(),
            ]);

            Log::error("GenererQuestionnairesJob : echec pour mission #{$mission->id}", [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Estime combien de questionnaires vont etre generes (1 par service, ou
     * 1 par pole si aucun service liste).
     */
    private function compterCibles(Organigramme $organigramme): int
    {
        $total = 0;
        foreach ($organigramme->structure ?? [] as $entree) {
            $services = $entree['services'] ?? [];
            $total += max(1, count($services));
        }

        return max(1, $total);
    }

    private function ecrireProgres(Organigramme $organigramme, array $patch): void
    {
        $metadata = $organigramme->metadata ?? [];
        $generation = ($metadata['generation'] ?? []) + $patch;
        $metadata['generation'] = array_merge($metadata['generation'] ?? [], $patch);
        $organigramme->update(['metadata' => $metadata]);
    }
}
