<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Traitement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Traitement>
 */
class TraitementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'reference' => 'TRT-' . now()->year . '-' . fake()->unique()->numerify('###'),
            'nom' => fake()->words(3, true),
            'statut' => 'brouillon',
            'finalite_principale' => fake()->paragraph(2),
            'bases_legales' => ['contrat'],
            'categories_personnes' => ['salaries'],
            'categories_donnees' => ['identite', 'contact'],
            'donnees_sensibles' => false,
            'duree_conservation_active_mois' => 60,
            'duree_archivage_mois' => 120,
            'transfert_hors_cedeao' => false,
            'saisi_par' => User::factory(),
        ];
    }

    public function valide(): static
    {
        return $this->state(fn () => [
            'statut' => 'valide',
            'valide_par' => User::factory(),
            'valide_at' => now(),
        ]);
    }

    public function pourClient(Client $client): static
    {
        return $this->state(fn () => ['client_id' => $client->id]);
    }
}
