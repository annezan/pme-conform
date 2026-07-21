<?php

namespace Tests\Feature;

use App\Models\SecteurActivite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecteurActiviteTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $admin;
    protected User $manager;
    protected User $consultant;

    protected function setUp(): void
    {
        parent::setUp();

        // Désactiver seulement le middleware d'audit qui cause des problèmes
        $this->withoutMiddleware(\App\Http\Middleware\AuditActivity::class);

        // Créer les rôles et permissions
        $this->artisan('db:seed', ['class' => 'RolesAndPermissionsSeeder']);

        // Créer les utilisateurs avec rôles
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');

        $this->consultant = User::factory()->create();
        $this->consultant->assignRole('consultant');
    }

    /** @test */
    public function admin_can_view_secteurs_activite()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/secteurs-activite');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'nom',
                        'description',
                        'code',
                        'is_actif',
                        'created_by',
                        'updated_by',
                        'deleted_by',
                        'created_at',
                        'updated_at'
                    ]
                ]
            ]);
    }

    /** @test */
    public function manager_can_view_secteurs_activite()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/secteurs-activite');

        $response->assertStatus(200);
    }

    /** @test */
    public function consultant_can_view_secteurs_activite()
    {
        Sanctum::actingAs($this->consultant);

        $response = $this->getJson('/api/secteurs-activite');

        $response->assertStatus(200);
    }

    /** @test */
    public function unauthenticated_user_cannot_view_secteurs_activite()
    {
        $response = $this->getJson('/api/secteurs-activite');

        $response->assertStatus(401);
    }

    /** @test */
    public function admin_can_create_secteur_activite()
    {
        Sanctum::actingAs($this->admin);

        $data = [
            'nom' => 'Test Secteur',
            'description' => 'Description du secteur de test',
            'code' => 'TEST',
            'is_actif' => true
        ];

        $response = $this->postJson('/api/secteurs-activite', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'nom',
                    'description',
                    'code',
                    'is_actif',
                    'created_by',
                    'updated_by',
                    'created_at',
                    'updated_at'
                ]
            ]);

        $this->assertDatabaseHas('secteurs_activite', [
            'nom' => 'Test Secteur',
            'code' => 'TEST',
            'is_actif' => true
        ]);
    }

    /** @test */
    public function manager_can_create_secteur_activite()
    {
        Sanctum::actingAs($this->manager);

        $data = [
            'nom' => 'Test Secteur Manager',
            'description' => 'Description du secteur de test par manager',
            'code' => 'MGR_TEST',
            'is_actif' => true
        ];

        $response = $this->postJson('/api/secteurs-activite', $data);

        $response->assertStatus(201);
    }

    /** @test */
    public function consultant_cannot_create_secteur_activite()
    {
        Sanctum::actingAs($this->consultant);

        $data = [
            'nom' => 'Test Secteur Consultant',
            'description' => 'Description du secteur de test par consultant',
            'code' => 'CONS_TEST',
            'is_actif' => true
        ];

        $response = $this->postJson('/api/secteurs-activite', $data);

        $response->assertStatus(403);
    }

    /** @test */
    public function secteur_activite_nom_must_be_unique()
    {
        Sanctum::actingAs($this->admin);

        // Créer un premier secteur
        $secteur1 = SecteurActivite::factory()->create(['nom' => 'Secteur Unique']);

        // Tenter de créer un deuxième avec le même nom
        $data = [
            'nom' => 'Secteur Unique',
            'description' => 'Description différente',
            'code' => 'DIFF',
            'is_actif' => true
        ];

        $response = $this->postJson('/api/secteurs-activite', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nom']);
    }

    /** @test */
    public function secteur_activite_code_must_be_unique()
    {
        Sanctum::actingAs($this->admin);

        // Créer un premier secteur
        $secteur1 = SecteurActivite::factory()->create(['code' => 'UNIQUE']);

        // Tenter de créer un deuxième avec le même code
        $data = [
            'nom' => 'Secteur Différent',
            'description' => 'Description différente',
            'code' => 'UNIQUE',
            'is_actif' => true
        ];

        $response = $this->postJson('/api/secteurs-activite', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    /** @test */
    public function admin_can_update_secteur_activite()
    {
        Sanctum::actingAs($this->admin);

        $secteur = SecteurActivite::factory()->create([
            'nom' => 'Ancien Nom',
            'code' => 'OLD_CODE'
        ]);

        $data = [
            'nom' => 'Nouveau Nom',
            'description' => 'Nouvelle description',
            'code' => 'NEW_CODE',
            'is_actif' => false
        ];

        $response = $this->putJson("/api/secteurs-activite/{$secteur->id}", $data);

        $response->assertStatus(200);

        // Rafraîchir le modèle pour obtenir les dernières données
        $secteur->refresh();

        $this->assertEquals('Nouveau Nom', $secteur->nom);
        $this->assertEquals('NEW_CODE', $secteur->code);
        $this->assertFalse($secteur->is_actif);

        $this->assertDatabaseHas('secteurs_activite', [
            'id' => $secteur->id,
            'nom' => 'Nouveau Nom',
            'code' => 'NEW_CODE',
            'is_actif' => false
        ]);
    }

    /** @test */
    public function manager_can_update_secteur_activite()
    {
        Sanctum::actingAs($this->manager);

        $secteur = SecteurActivite::factory()->create();

        $data = [
            'nom' => 'Nom modifié par manager',
            'description' => 'Description modifiée',
            'is_actif' => true
        ];

        $response = $this->putJson("/api/secteurs-activite/{$secteur->id}", $data);

        $response->assertStatus(200);
    }

    /** @test */
    public function consultant_cannot_update_secteur_activite()
    {
        Sanctum::actingAs($this->consultant);

        $secteur = SecteurActivite::factory()->create();

        $data = [
            'nom' => 'Nom modifié par consultant',
            'description' => 'Description modifiée',
            'is_actif' => true
        ];

        $response = $this->putJson("/api/secteurs-activite/{$secteur->id}", $data);

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_delete_secteur_activite()
    {
        Sanctum::actingAs($this->admin);

        $secteur = SecteurActivite::factory()->create();

        $response = $this->deleteJson("/api/secteurs-activite/{$secteur->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'deleted_at'
                ]
            ]);

        $this->assertSoftDeleted('secteurs_activite', ['id' => $secteur->id]);
    }

    /** @test */
    public function manager_cannot_delete_secteur_activite()
    {
        Sanctum::actingAs($this->manager);

        $secteur = SecteurActivite::factory()->create();

        $response = $this->deleteJson("/api/secteurs-activite/{$secteur->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function consultant_cannot_delete_secteur_activite()
    {
        Sanctum::actingAs($this->consultant);

        $secteur = SecteurActivite::factory()->create();

        $response = $this->deleteJson("/api/secteurs-activite/{$secteur->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_toggle_secteur_activite_status()
    {
        Sanctum::actingAs($this->admin);

        $secteur = SecteurActivite::factory()->create(['is_actif' => true]);

        $response = $this->getJson("/api/secteurs-activite/{$secteur->id}/toggle-actif");

        $response->assertStatus(200);

        $this->assertDatabaseHas('secteurs_activite', [
            'id' => $secteur->id,
            'is_actif' => false
        ]);
    }

    /** @test */
    public function can_search_secteurs_activite()
    {
        Sanctum::actingAs($this->admin);

        // Créer des secteurs de test
        SecteurActivite::factory()->create(['nom' => 'Banque et Finance']);
        SecteurActivite::factory()->create(['nom' => 'Santé']);
        SecteurActivite::factory()->create(['nom' => 'Industrie']);

        $response = $this->getJson('/api/secteurs-activite?search=Banque');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nom', 'Banque et Finance');
    }

    /** @test */
    public function can_filter_secteurs_activite_by_status()
    {
        Sanctum::actingAs($this->admin);

        // Créer des secteurs avec différents statuts
        SecteurActivite::factory()->create(['nom' => 'Secteur Actif', 'is_actif' => true]);
        SecteurActivite::factory()->create(['nom' => 'Secteur Inactif', 'is_actif' => false]);

        $response = $this->getJson('/api/secteurs-activite?is_actif=false');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nom', 'Secteur Inactif')
            ->assertJsonPath('data.0.is_actif', false);
    }

    /** @test */
    public function audit_columns_are_filled_on_creation()
    {
        Sanctum::actingAs($this->admin);

        $data = [
            'nom' => 'Secteur avec audit',
            'description' => 'Test des colonnes d\'audit',
            'code' => 'AUDIT',
            'is_actif' => true
        ];

        $response = $this->postJson('/api/secteurs-activite', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('secteurs_activite', [
            'nom' => 'Secteur avec audit',
            'created_by' => $this->admin->id,
        ]);
    }
}
