<?php

namespace Database\Factories;

use App\Models\Agent;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'nom' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'prompt_systeme' => 'Tu es un assistant IA. Reponds en francais.',
            'type' => fake()->randomElement(['conversationnel', 'analytique', 'generateur']),
            'is_active' => true,
            'is_core' => false,
            'temperature' => 0.7,
            'ordre_affichage' => 0,
        ];
    }
}
