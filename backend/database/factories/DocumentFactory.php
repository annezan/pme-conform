<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'uploaded_by' => User::factory(),
            'titre' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'nom_fichier_original' => fake()->word() . '.pdf',
            'type_mime' => 'application/pdf',
            'taille_octets' => fake()->numberBetween(10000, 5000000),
            'type' => 'document_client',
            'statut' => 'en_attente',
            'is_confidentiel' => true,
        ];
    }
}
