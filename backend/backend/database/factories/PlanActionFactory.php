<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\PlanAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanAction>
 */
class PlanActionFactory extends Factory
{
    protected $model = PlanAction::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'reference' => 'PA-' . now()->year . '-' . fake()->unique()->numerify('###'),
            'titre' => fake()->sentence(4),
            'objectif' => fake()->paragraph(),
            'propose_par' => User::factory(),
            'statut' => 'propose',
        ];
    }

    public function accepte(): static
    {
        return $this->state(fn () => [
            'statut' => 'accepte_client',
            'accepte_le' => now(),
            'accepte_par' => User::factory(),
        ]);
    }

    public function cloture(): static
    {
        return $this->state(fn () => [
            'statut' => 'cloture',
            'cloture_le' => now(),
        ]);
    }
}
