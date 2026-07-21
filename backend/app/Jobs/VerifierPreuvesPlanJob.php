<?php

/**
 * Job VerifierPreuvesPlanJob — Verifie toutes les preuves d'un plan d'action.
 *
 * Declenche par PlanActionController::soumettre quand le client clique
 * "Soumettre au consultant" sur PlanActionDetail.
 *
 * Pour chaque item du plan :
 *   1. Si pas de preuve : item->verdict = 'non_evalue', skip.
 *   2. Sinon : agrege le texte de TOUTES les preuves de l'item, appelle
 *      PreuveVerifierService(ecart.recommandation, contenu_agrege), sauvegarde
 *      verdict + justification + verifie_le.
 *   3. Met a jour plan.verification_progression_pct apres chaque item.
 *
 * En fin de job, notifie le consultant ASC propose_par du plan.
 *
 * IMPORTANT : on NE recree PAS de nouveaux ecarts (objectif explicite de
 * l'utilisateur). Le moteur d'analyse classique (AnalyseController::refaire)
 * reste disponible pour le consultant s'il veut une re-detection complete.
 */

namespace App\Jobs;

use App\Models\PlanAction;
use App\Models\PlanActionItem;
use App\Models\User;
use App\Notifications\PreuvesSoumisesPourValidation;
use App\Services\Analyse\PreuveVerifierService;
use App\Services\Document\ExtractorFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VerifierPreuvesPlanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 7200; // 2h, large marge

    public function __construct(
        private PlanAction $plan,
    ) {
        $this->onQueue('analyses');
    }

    public function handle(
        PreuveVerifierService $verifier,
        ExtractorFactory $extractorFactory,
    ): void {
        @set_time_limit(0);
        @ignore_user_abort(true);

        $this->plan->update([
            'verification_statut' => 'en_cours',
            'verification_progression_pct' => 0,
        ]);

        $items = $this->plan->items()->with('ecart', 'preuves')->get();
        $total = max(1, $items->count());
        $i = 0;

        $statsVerdicts = ['conforme' => 0, 'partielle' => 0, 'non_conforme' => 0, 'non_evalue' => 0];

        foreach ($items as $item) {
            $i++;

            // Pas d'ecart lie ou pas de recommandation : on ne peut pas evaluer
            $recommandation = $item->ecart?->recommandation;
            if (! $recommandation) {
                $item->update([
                    'verdict_correction' => 'non_evalue',
                    'justification_correction' => 'Item non lie a un ecart ou sans recommandation : verification impossible.',
                    'verifie_le' => now(),
                ]);
                $statsVerdicts['non_evalue']++;
                $this->majProgression($i, $total);
                continue;
            }

            // Aucune preuve : on marque non_evalue avec une justification claire
            if ($item->preuves->isEmpty()) {
                $item->update([
                    'verdict_correction' => 'non_evalue',
                    'justification_correction' => 'Aucune preuve fournie par le client pour cette action.',
                    'verifie_le' => now(),
                ]);
                $statsVerdicts['non_evalue']++;
                $this->majProgression($i, $total);
                continue;
            }

            // Agrege le texte de toutes les preuves de l'item. Le LLM evalue
            // l'ENSEMBLE des preuves par rapport a la recommandation (peut etre
            // qu'une seule preuve couvre tout, ou qu'il en faut plusieurs).
            $contenuAgrege = '';
            foreach ($item->preuves as $preuve) {
                $contenuAgrege .= "\n--- Preuve : {$preuve->libelle} ---\n";
                $contenuAgrege .= $this->lireContenuPreuve($preuve, $extractorFactory);
                $contenuAgrege .= "\n";
            }

            $exigence = $item->ecart?->exigence_referentiel;
            $resultat = $verifier->verifier($recommandation, trim($contenuAgrege), $exigence);

            $verdict = $resultat['verdict'] ?? 'non_evalue';
            $justification = $resultat['justification']
                ?? 'Le moteur LLM n\'a pas pu produire de verdict (timeout ou reponse invalide). Verification manuelle requise.';

            $item->update([
                'verdict_correction' => $verdict,
                'justification_correction' => $justification,
                'verifie_le' => now(),
            ]);

            $statsVerdicts[$verdict] = ($statsVerdicts[$verdict] ?? 0) + 1;
            $this->majProgression($i, $total);
        }

        $this->plan->update([
            'verification_statut' => 'terminee',
            'verification_progression_pct' => 100,
        ]);

        // Notification au consultant ASC (proposeur du plan). Si pas de proposeur
        // identifie (anciens plans), on notifie tous les users avec
        // view-all-plans-actions sur ce client.
        $this->notifierConsultant($statsVerdicts);
    }

    /**
     * Lit le contenu textuel d'une preuve. Utilise le cache contenu_extrait
     * pour eviter de re-extraire a chaque relance.
     */
    private function lireContenuPreuve(\App\Models\PlanActionItemPreuve $preuve, ExtractorFactory $factory): string
    {
        if (! empty($preuve->contenu_extrait)) {
            return $preuve->contenu_extrait;
        }

        try {
            if (! Storage::disk('local')->exists($preuve->chemin)) {
                return '(Fichier introuvable sur le disque.)';
            }
            $cheminAbsolu = Storage::disk('local')->path($preuve->chemin);

            if (! $factory->supporte($preuve->mime ?? '')) {
                return '(Type MIME non supporte pour l\'extraction : ' . $preuve->mime . ')';
            }

            $contenu = $factory->extraire($cheminAbsolu, $preuve->mime);
            // Cache pour eviter de relire le fichier au prochain run
            $preuve->update(['contenu_extrait' => mb_substr($contenu, 0, 50000)]);

            return $contenu;
        } catch (\Throwable $e) {
            Log::warning('VerifierPreuves : extraction echouee', [
                'preuve_id' => $preuve->id,
                'error' => $e->getMessage(),
            ]);
            return '(Erreur d\'extraction du contenu : ' . $e->getMessage() . ')';
        }
    }

    private function majProgression(int $i, int $total): void
    {
        $pct = (int) round(($i / $total) * 100);
        $this->plan->update(['verification_progression_pct' => $pct]);
    }

    private function notifierConsultant(array $statsVerdicts): void
    {
        $consultants = collect();

        if ($this->plan->propose_par) {
            $proposeur = User::find($this->plan->propose_par);
            if ($proposeur) {
                $consultants->push($proposeur);
            }
        }

        if ($consultants->isEmpty()) {
            // Fallback : tous les users avec view-all-plans-actions sur ce client
            $consultants = User::permission('view-all-plans-actions')
                ->whereHas('clients', fn ($q) => $q->where('clients.id', $this->plan->client_id))
                ->get();
        }

        foreach ($consultants as $consultant) {
            $consultant->notify(new PreuvesSoumisesPourValidation($this->plan, $statsVerdicts));
        }
    }
}
