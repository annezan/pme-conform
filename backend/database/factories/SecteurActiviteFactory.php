<?php

namespace Database\Factories;

use App\Models\SecteurActivite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SecteurActivite>
 */
class SecteurActiviteFactory extends Factory
{
    protected $model = SecteurActivite::class;

    public function definition(): array
    {
        return [
            'nom' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(10),
            'code' => fake()->unique()->lexify('???'),
            'is_actif' => true,
        ];
    }

    /**
     * Indicate that the secteur is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_actif' => false,
        ]);
    }
}
