<?php

/**
 * Notification PreuvesSoumisesPourValidation — Envoyee au consultant ASC
 * apres que le job VerifierPreuvesPlanJob a fini d'evaluer les preuves
 * deposees par le client sur un plan d'action.
 *
 * Canal : database (in-app, bell icon). Pas d'email pour l'instant —
 * peut etre ajoute facilement via via() si besoin.
 */

namespace App\Notifications;

use App\Models\PlanAction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PreuvesSoumisesPourValidation extends Notification
{
    use Queueable;

    public function __construct(
        public PlanAction $plan,
        public array $statsVerdicts,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $client = $this->plan->client;
        $titre = "Plan {$this->plan->reference} soumis pour validation";

        $resume = sprintf(
            '%d conforme · %d partielle · %d non conforme · %d non evalue',
            $this->statsVerdicts['conforme'] ?? 0,
            $this->statsVerdicts['partielle'] ?? 0,
            $this->statsVerdicts['non_conforme'] ?? 0,
            $this->statsVerdicts['non_evalue'] ?? 0,
        );

        return [
            'type' => 'preuves_soumises_pour_validation',
            'plan_id' => $this->plan->id,
            'plan_reference' => $this->plan->reference,
            'plan_titre' => $this->plan->titre,
            'client_id' => $client?->id,
            'client_raison_sociale' => $client?->raison_sociale,
            'titre' => $titre,
            'message' => $client
                ? "Le client {$client->raison_sociale} a soumis des preuves pour validation. Verdicts : {$resume}."
                : "Des preuves ont ete soumises pour validation. Verdicts : {$resume}.",
            'stats_verdicts' => $this->statsVerdicts,
            'url' => "/plans-actions/{$this->plan->id}",
        ];
    }
}
