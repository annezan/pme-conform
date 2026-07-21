<?php

namespace Tests\Unit;

use App\Models\SecteurActivite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecteurActiviteModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function secteur_activite_can_be_created()
    {
        $secteur = SecteurActivite::create([
            'nom' => 'Test Secteur',
            'description' => 'Description de test',
            'code' => 'TEST',
            'is_actif' => true
        ]);

        $this->assertInstanceOf(SecteurActivite::class, $secteur);
        $this->assertEquals('Test Secteur', $secteur->nom);
        $this->assertEquals('Description de test', $secteur->description);
        $this->assertEquals('TEST', $secteur->code);
        $this->assertTrue($secteur->is_actif);
    }

    /** @test */
    public function secteur_activite_uses_soft_deletes()
    {
        $secteur = SecteurActivite::create([
            'nom' => 'Test Secteur',
            'code' => 'TEST'
        ]);

        $secteur->delete();

        $this->assertSoftDeleted('secteurs_activite', ['id' => $secteur->id]);
        $this->assertNotNull($secteur->deleted_at);
    }

    /** @test */
    public function secteur_activite_has_fillable_fields()
    {
        $secteur = new SecteurActivite();

        $fillable = ['nom', 'description', 'code', 'is_actif', 'created_by', 'updated_by', 'deleted_by'];

        $this->assertEquals($fillable, $secteur->getFillable());
    }

    /** @test */
    public function secteur_activite_casts_boolean_fields()
    {
        $secteur = SecteurActivite::create([
            'nom' => 'Test Secteur',
            'code' => 'TEST',
            'is_actif' => true
        ]);

        $this->assertIsBool($secteur->is_actif);
        $this->assertTrue($secteur->is_actif);

        $secteur->update(['is_actif' => false]);
        $this->assertFalse($secteur->is_actif);
    }

    /** @test */
    public function secteur_activite_nom_must_be_unique()
    {
        SecteurActivite::create(['nom' => 'Unique Nom', 'code' => 'UNIQUE1']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        SecteurActivite::create(['nom' => 'Unique Nom', 'code' => 'UNIQUE2']);
    }

    /** @test */
    public function secteur_activite_code_must_be_unique_when_not_null()
    {
        SecteurActivite::create(['nom' => 'Test 1', 'code' => 'UNIQUE_CODE']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        SecteurActivite::create(['nom' => 'Test 2', 'code' => 'UNIQUE_CODE']);
    }

    /** @test */
    public function secteur_activite_code_can_be_null()
    {
        $secteur = SecteurActivite::create([
            'nom' => 'Test Sans Code',
            'code' => null
        ]);

        $this->assertNull($secteur->code);
        $this->assertDatabaseHas('secteurs_activite', [
            'id' => $secteur->id,
            'code' => null
        ]);
    }

    /** @test */
    public function secteur_activite_has_created_by_relation()
    {
        $user = User::factory()->create();
        
        $secteur = SecteurActivite::create([
            'nom' => 'Test Secteur',
            'code' => 'TEST',
            'created_by' => $user->id
        ]);

        $this->assertInstanceOf(User::class, $secteur->createdBy);
        $this->assertEquals($user->id, $secteur->createdBy->id);
    }

    /** @test */
    public function secteur_activite_has_updated_by_relation()
    {
        $user = User::factory()->create();
        
        $secteur = SecteurActivite::create([
            'nom' => 'Test Secteur',
            'code' => 'TEST',
            'updated_by' => $user->id
        ]);

        $this->assertInstanceOf(User::class, $secteur->updatedBy);
        $this->assertEquals($user->id, $secteur->updatedBy->id);
    }

    /** @test */
    public function secteur_activite_has_deleted_by_relation()
    {
        $user = User::factory()->create();
        
        $secteur = SecteurActivite::create([
            'nom' => 'Test Secteur',
            'code' => 'TEST'
        ]);

        $secteur->delete();
        $secteur->deleted_by = $user->id;
        $secteur->save();

        $this->assertInstanceOf(User::class, $secteur->deletedBy);
        $this->assertEquals($user->id, $secteur->deletedBy->id);
    }

    /** @test */
    public function secteur_activite_scope_active_filters_active_secteurs()
    {
        // Créer des secteurs avec différents statuts
        $activeSecteur = SecteurActivite::create(['nom' => 'Actif', 'code' => 'ACT', 'is_actif' => true]);
        $inactiveSecteur = SecteurActivite::create(['nom' => 'Inactif', 'code' => 'INACT', 'is_actif' => false]);

        $activeSecteurs = SecteurActivite::active()->get();

        $this->assertCount(1, $activeSecteurs);
        $this->assertEquals($activeSecteur->id, $activeSecteurs->first()->id);
    }

    /** @test */
    public function secteur_activite_scope_inactive_filters_inactive_secteurs()
    {
        // Créer des secteurs avec différents statuts
        $activeSecteur = SecteurActivite::create(['nom' => 'Actif', 'code' => 'ACT', 'is_actif' => true]);
        $inactiveSecteur = SecteurActivite::create(['nom' => 'Inactif', 'code' => 'INACT', 'is_actif' => false]);

        $inactiveSecteurs = SecteurActivite::inactive()->get();

        $this->assertCount(1, $inactiveSecteurs);
        $this->assertEquals($inactiveSecteur->id, $inactiveSecteurs->first()->id);
    }

    /** @test */
    public function secteur_activite_scope_search_filters_by_nom_or_description()
    {
        // Créer des secteurs de test
        $secteur1 = SecteurActivite::create(['nom' => 'Banque et Finance', 'description' => 'Services financiers']);
        $secteur2 = SecteurActivite::create(['nom' => 'Santé', 'description' => 'Services médicaux']);
        $secteur3 = SecteurActivite::create(['nom' => 'Industrie', 'description' => 'Production industrielle']);

        // Recherche par nom
        $results = SecteurActivite::search('Banque')->get();
        $this->assertCount(1, $results);
        $this->assertEquals($secteur1->id, $results->first()->id);

        // Recherche par description
        $results = SecteurActivite::search('médicaux')->get();
        $this->assertCount(1, $results);
        $this->assertEquals($secteur2->id, $results->first()->id);

        // Recherche partielle
        $results = SecteurActivite::search('industr')->get();
        $this->assertCount(1, $results);
        $this->assertEquals($secteur3->id, $results->first()->id);
    }

    /** @test */
    public function secteur_activite_scope_by_code_filters_by_code()
    {
        $secteur1 = SecteurActivite::create(['nom' => 'Test 1', 'code' => 'CODE1']);
        $secteur2 = SecteurActivite::create(['nom' => 'Test 2', 'code' => 'CODE2']);

        $results = SecteurActivite::byCode('CODE1')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($secteur1->id, $results->first()->id);
    }

    /** @test */
    public function secteur_activite_logs_activity()
    {
        $secteur = SecteurActivite::create([
            'nom' => 'Test Secteur',
            'code' => 'TEST'
        ]);

        // Vérifier que l'activité est loggée
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => get_class($secteur),
            'subject_id' => $secteur->id,
            'description' => 'created'
        ]);
    }

    /** @test */
    public function secteur_activite_to_array_includes_relations()
    {
        $user = User::factory()->create();
        
        $secteur = SecteurActivite::create([
            'nom' => 'Test Secteur',
            'code' => 'TEST',
            'created_by' => $user->id,
            'updated_by' => $user->id
        ]);

        $array = $secteur->toArray();

        $this->assertArrayHasKey('created_by', $array);
        $this->assertArrayHasKey('updated_by', $array);
        $this->assertArrayHasKey('deleted_by', $array);
    }

    /** @test */
    public function secteur_activite_default_values()
    {
        $secteur = SecteurActivite::create([
            'nom' => 'Test Secteur',
            'code' => 'TEST'
        ]);

        $this->assertTrue($secteur->is_actif); // is_actif doit être true par défaut
    }

    /** @test */
    public function secteur_activite_can_be_ordered_by_nom()
    {
        $secteurC = SecteurActivite::create(['nom' => 'C Secteur', 'code' => 'C']);
        $secteurA = SecteurActivite::create(['nom' => 'A Secteur', 'code' => 'A']);
        $secteurB = SecteurActivite::create(['nom' => 'B Secteur', 'code' => 'B']);

        $orderedSecteurs = SecteurActivite::orderBy('nom')->get();

        $this->assertEquals($secteurA->id, $orderedSecteurs[0]->id);
        $this->assertEquals($secteurB->id, $orderedSecteurs[1]->id);
        $this->assertEquals($secteurC->id, $orderedSecteurs[2]->id);
    }

    /** @test */
    public function secteur_activite_can_be_filtered_by_multiple_criteria()
    {
        $secteur1 = SecteurActivite::create([
            'nom' => 'Active Bank',
            'code' => 'BANK',
            'is_actif' => true
        ]);
        
        $secteur2 = SecteurActivite::create([
            'nom' => 'Inactive Health',
            'code' => 'HEALTH',
            'is_actif' => false
        ]);

        $secteur3 = SecteurActivite::create([
            'nom' => 'Active Tech',
            'code' => 'TECH',
            'is_actif' => true
        ]);

        // Combinaison de filtres
        $results = SecteurActivite::active()->search('Active')->get();
        
        $this->assertCount(2, $results);
        $this->assertContains($secteur1->id, $results->pluck('id'));
        $this->assertContains($secteur3->id, $results->pluck('id'));
        $this->assertNotContains($secteur2->id, $results->pluck('id'));
    }
}
