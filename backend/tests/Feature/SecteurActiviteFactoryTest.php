<?php

namespace Tests\Feature;

use App\Models\SecteurActivite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecteurActiviteFactoryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function secteur_activite_factory_creates_valid_secteur()
    {
        $secteur = SecteurActivite::factory()->create();

        $this->assertInstanceOf(SecteurActivite::class, $secteur);
        $this->assertNotNull($secteur->nom);
        $this->assertNotNull($secteur->code);
        $this->assertIsBool($secteur->is_actif);
        $this->assertDatabaseHas('secteurs_activite', ['id' => $secteur->id]);
    }

    /** @test */
    public function secteur_activite_factory_with_inactive_state()
    {
        $secteur = SecteurActivite::factory()->inactive()->create();

        $this->assertFalse($secteur->is_actif);
        $this->assertDatabaseHas('secteurs_activite', [
            'id' => $secteur->id,
            'is_actif' => false
        ]);
    }

    /** @test */
    public function secteur_activite_factory_with_specific_data()
    {
        $secteur = SecteurActivite::factory()->create([
            'nom' => 'Banque et Finance',
            'code' => 'BANK_FIN',
            'is_actif' => false
        ]);

        $this->assertEquals('Banque et Finance', $secteur->nom);
        $this->assertEquals('BANK_FIN', $secteur->code);
        $this->assertFalse($secteur->is_actif);
    }

    /** @test */
    public function secteur_activite_factory_creates_multiple_secteurs()
    {
        $count = 5;
        $secteurs = SecteurActivite::factory()->count($count)->create();

        $this->assertCount($count, $secteurs);
        $this->assertDatabaseCount('secteurs_activite', $count);
    }

    /** @test */
    public function secteur_activite_factory_creates_unique_names()
    {
        $secteurs = SecteurActivite::factory()->count(10)->create();

        $names = $secteurs->pluck('nom')->unique();
        $this->assertCount(10, $names); // Tous les noms doivent être uniques
    }

    /** @test */
    public function secteur_activite_factory_creates_unique_codes()
    {
        $secteurs = SecteurActivite::factory()->count(10)->create();

        $codes = $secteurs->pluck('code')->unique();
        $this->assertCount(10, $codes); // Tous les codes doivent être uniques
    }
}
