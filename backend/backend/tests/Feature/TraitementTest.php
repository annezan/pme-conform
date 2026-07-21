<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Traitement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TraitementTest extends TestCase
{
    use RefreshDatabase;

    private User $clientAdmin;
    private User $clientAutre;
    private Client $entreprise;
    private Client $entrepriseAutre;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->entreprise = Client::factory()->create();
        $this->entrepriseAutre = Client::factory()->create();

        $this->clientAdmin = User::factory()->create();
        $this->clientAdmin->assignRole('client_admin');
        $this->clientAdmin->clients()->attach($this->entreprise->id, ['role_projet' => 'titulaire']);

        $this->clientAutre = User::factory()->create();
        $this->clientAutre->assignRole('client_admin');
        $this->clientAutre->clients()->attach($this->entrepriseAutre->id, ['role_projet' => 'titulaire']);
    }

    public function test_un_client_admin_peut_creer_un_traitement(): void
    {
        $payload = [
            'nom' => 'Gestion de la paie',
            'finalite_principale' => 'Calcul et versement des salaires mensuels',
            'bases_legales' => ['contrat', 'obligation_legale'],
            'categories_personnes' => ['salaries'],
            'categories_donnees' => ['identite', 'banque'],
        ];

        $response = $this->actingAs($this->clientAdmin)->postJson('/api/traitements', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('traitements', [
            'nom' => 'Gestion de la paie',
            'client_id' => $this->entreprise->id,
            'statut' => 'brouillon',
        ]);
        // Une revision de creation doit etre enregistree
        $traitement = Traitement::first();
        $this->assertEquals(1, $traitement->revisions()->count());
    }

    public function test_un_client_ne_peut_pas_voir_les_traitements_dun_autre_client(): void
    {
        $traitementAutre = Traitement::factory()->pourClient($this->entrepriseAutre)->create();

        $response = $this->actingAs($this->clientAdmin)->getJson("/api/traitements/{$traitementAutre->id}");

        $response->assertForbidden();
    }

    public function test_list_traitements_est_scope_par_client(): void
    {
        Traitement::factory()->pourClient($this->entreprise)->count(2)->create();
        Traitement::factory()->pourClient($this->entrepriseAutre)->count(3)->create();

        $response = $this->actingAs($this->clientAdmin)->getJson('/api/traitements');

        $response->assertOk();
        // Le clientAdmin ne voit que les 2 traitements de SON entreprise
        $this->assertCount(2, $response->json('data'));
    }

    public function test_validation_refuse_si_champs_obligatoires_manquants(): void
    {
        $traitement = Traitement::factory()->pourClient($this->entreprise)->create([
            'nom' => 'Test', // OK
            'finalite_principale' => '', // vide
        ]);

        $response = $this->actingAs($this->clientAdmin)
            ->postJson("/api/traitements/{$traitement->id}/valider");

        $response->assertStatus(422);
    }

    public function test_validation_requiert_role_client_admin(): void
    {
        $clientSimple = User::factory()->create();
        $clientSimple->assignRole('client');
        $clientSimple->clients()->attach($this->entreprise->id);

        $traitement = Traitement::factory()->pourClient($this->entreprise)->create();

        $response = $this->actingAs($clientSimple)
            ->postJson("/api/traitements/{$traitement->id}/valider");

        $response->assertForbidden();
    }

    public function test_modification_dun_traitement_valide_le_repasse_en_brouillon(): void
    {
        $traitement = Traitement::factory()
            ->pourClient($this->entreprise)
            ->valide()
            ->create([
                'bases_legales' => ['contrat'],
                'categories_personnes' => ['salaries'],
                'categories_donnees' => ['identite'],
            ]);

        $response = $this->actingAs($this->clientAdmin)
            ->putJson("/api/traitements/{$traitement->id}", ['nom' => 'Nouveau nom']);

        $response->assertOk();
        $this->assertEquals('brouillon', $traitement->fresh()->statut);
        $this->assertNull($traitement->fresh()->valide_par);
    }

    public function test_suppression_autorisee_uniquement_sur_brouillon(): void
    {
        $traitementValide = Traitement::factory()->pourClient($this->entreprise)->valide()->create();
        $response = $this->actingAs($this->clientAdmin)->deleteJson("/api/traitements/{$traitementValide->id}");
        $response->assertForbidden();

        $traitementBrouillon = Traitement::factory()->pourClient($this->entreprise)->create([
            'saisi_par' => $this->clientAdmin->id,
        ]);
        $response = $this->actingAs($this->clientAdmin)->deleteJson("/api/traitements/{$traitementBrouillon->id}");
        $response->assertOk();
    }

    public function test_historique_retourne_timeline_des_revisions(): void
    {
        $traitement = Traitement::factory()->pourClient($this->entreprise)->create([
            'bases_legales' => ['contrat'],
            'categories_personnes' => ['salaries'],
            'categories_donnees' => ['identite'],
        ]);

        // Modifier 2 fois
        $this->actingAs($this->clientAdmin)
            ->putJson("/api/traitements/{$traitement->id}", ['nom' => 'v2']);
        $this->actingAs($this->clientAdmin)
            ->putJson("/api/traitements/{$traitement->id}", ['nom' => 'v3']);

        $response = $this->actingAs($this->clientAdmin)->getJson("/api/traitements/{$traitement->id}/historique");

        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, count($response->json('timeline')));
    }
}
