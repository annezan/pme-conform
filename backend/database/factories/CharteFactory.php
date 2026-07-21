<?php

namespace Database\Factories;

use App\Models\Charte;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Charte>
 */
class CharteFactory extends Factory
{
    public function definition(): array
    {
        $contenu = '<h1>' . fake()->sentence() . '</h1><p>' . fake()->paragraphs(3, true) . '</p>';

        return [
            'type' => 'charte_ia',
            'titre' => fake()->sentence(4),
            'version' => fake()->numerify('#.0'),
            'contenu_html' => $contenu,
            'hash_contenu' => Charte::calculerHash($contenu),
            'active' => true,
            'obligatoire' => true,
            'publiee_le' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
