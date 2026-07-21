<?php

/**
 * Commande Artisan pour peupler la table des secteurs d'activité
 * avec les valeurs par défaut depuis SECTEURS_DEFAUT.
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedSecteursActivite extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'secteurs:seed {--force : Forcer le re-peuplement même si des données existent}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Peupler la table des secteurs d\'activité avec les valeurs par défaut';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Début du peuplement des secteurs d\'activité...');

        // Vérifier si des données existent déjà
        $count = DB::table('secteurs_activite')->count();
        
        if ($count > 0 && !$this->option('force')) {
            $this->warn("La table secteurs_activite contient déjà {$count} enregistrements.");
            $this->warn('Utilisez --force pour supprimer les données existantes et repeupler.');
            return self::FAILURE;
        }

        // Si --force est utilisé, vider la table d'abord
        if ($this->option('force')) {
            DB::table('secteurs_activite')->truncate();
            $this->info('Table secteurs_activite vidée.');
        }

        // Exécuter le seeder
        $this->call('db:seed', [
            '--class' => 'Database\\Seeders\\SecteursActiviteSeeder'
        ]);

        $this->info('✅ Secteurs d\'activité peuplés avec succès!');
        
        // Afficher un résumé
        $newCount = DB::table('secteurs_activite')->count();
        $this->info("📊 Total des secteurs créés : {$newCount}");

        return self::SUCCESS;
    }
}
