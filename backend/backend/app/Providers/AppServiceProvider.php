<?php

/**
 * Service Provider principal de l'application ASC-IA.
 *
 * Enregistre les bindings du conteneur IoC pour tous les services du noyau.
 */

namespace App\Providers;

use App\Contracts\LLMConnectorInterface;
use App\Contracts\OrchestratorInterface;
use App\Contracts\RetrievalInterface;
use App\Models\Mission;
use App\Models\PlanAction;
use App\Models\Traitement;
use App\Policies\MissionPolicy;
use App\Policies\PlanActionPolicy;
use App\Policies\TraitementPolicy;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\Gate;
use App\Services\Document\ExtractorFactory;
use App\Services\LLM\OllamaConnector;
use App\Services\Orchestrator\OrchestratorService;
use App\Services\RAG\PgvectorChecker;
use App\Services\RAG\RetrievalService;
use App\Services\Security\PseudonymizationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;
use Pgvector\Laravel\Schema as PgvectorSchema;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // LLM
        $this->app->singleton(LLMConnectorInterface::class, OllamaConnector::class);

        // Orchestrateur
        $this->app->singleton(OrchestratorInterface::class, OrchestratorService::class);

        // RAG
        $this->app->singleton(RetrievalInterface::class, RetrievalService::class);
        $this->app->singleton(PgvectorChecker::class);

        // Extraction de documents
        $this->app->singleton(ExtractorFactory::class);

        // Audit
        $this->app->singleton(AuditService::class);

        // Pseudonymisation — NON singleton (etat par requete)
        $this->app->bind(PseudonymizationService::class);
    }

    public function boot(): void
    {
        PgvectorSchema::register();

        // Macro pour les colonnes d'audit
        Blueprint::macro('auditColumns', function () {
            $this->foreignId('created_by')->nullable();
            $this->foreignId('updated_by')->nullable();
            $this->foreignId('deleted_by')->nullable();
        });

        // Policies PME-CONFORM
        Gate::policy(Traitement::class, TraitementPolicy::class);
        Gate::policy(PlanAction::class, PlanActionPolicy::class);
        Gate::policy(Mission::class, MissionPolicy::class);

        // Bridge entre le modele User custom (un seul role_id) et les
        // permissions Spatie attachees au role. Sans ce hook, $user->can('xxx')
        // ne consulterait pas les permissions du role et retournerait toujours false.
        //
        // Convention RolesAndPermissionsSeeder :
        //  - admin : bypass Gate::before ci-dessous (voit tout)
        //  - autres roles : permissions granulaires view-* / create-* / update-* / delete-*
        //    plus eventuellement view-all-* (bypass scope client) et manage-*
        //    (vraie CRUD administrative sur users, roles, modules...).
        //
        // Deux expansions umbrella :
        //  - `view-{X}` est implicitement couvert par `view-all-{X}` (voir tous
        //    les X implique voir les siens).
        //  - Tous les verbes (view/create/update/delete/...) sont couverts par
        //    `manage-{X}` (vraie gestion administrative, cf. manage-users).
        Gate::before(function ($user, string $ability) {
            if (! $user || ! ($user instanceof \App\Models\User)) {
                return null;
            }
            $role = $user->role;
            if (! $role) {
                return null;
            }

            // By-pass admin : le seeder declare explicitement que l'admin
            // possede toutes les permissions, on respecte cette intention.
            if ($role->name === 'admin') {
                return true;
            }

            if (! $role->relationLoaded('permissions')) {
                $role->load('permissions:id,name');
            }

            // Match exact sur le nom de la permission
            if ($role->permissions->contains('name', $ability)) {
                return true;
            }

            // Deux expansions umbrella :
            //  1. `view-all-{X}` couvre `view-{X}` (bypass scope implique voir)
            //  2. `manage-{X}` couvre tous les verbes (vraie CRUD admin)
            if (preg_match('/^(view|create|update|delete|accept|close|sign|revoke|download|generate|regenerate|upload|input|validate|archive|submit|freeze|reindex|enrich|cancel|restart)-(.+)$/', $ability, $m)) {
                $verbe = $m[1];
                $entite = $m[2];

                // (1) view-all-{X} implique view-{X}
                if ($verbe === 'view') {
                    $viewAllUmbrella = 'view-all-' . $entite;
                    if ($role->permissions->contains('name', $viewAllUmbrella)) {
                        return true;
                    }
                }

                // (2) manage-{X} umbrella pour vrai CRUD admin (users, roles, ...)
                $manageUmbrella = 'manage-' . $entite;
                if ($role->permissions->contains('name', $manageUmbrella)) {
                    return true;
                }
            }

            return null;
        });
    }
}
