<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'raison_sociale' => fake()->company(),
            'sigle' => fake()->lexify('???'),
            'secteur_activite' => fake()->randomElement(['banque', 'telecom', 'sante', 'industrie', 'services']),
            'type_structure' => 'pme',
            'statut' => 'actif',
            'ville' => fake()->city(),
            'pays' => 'Cote d\'Ivoire',
            'email' => fake()->companyEmail(),
            'telephone' => fake()->phoneNumber(),
            'numero_rccm' => fake()->bothify('CI-ABJ-####-?#####'),
            'effectif' => fake()->numberBetween(5, 500),
        ];
    }
}
