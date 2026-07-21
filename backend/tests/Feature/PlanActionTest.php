<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PlanAction;
use App\Models\PlanActionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanActionTest extends TestCase
{
    use RefreshDatabase;

    private User $consultant;
    private User $clientAdmin;
    private User $autreClient;
    private Client $entreprise;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->entreprise = Client::factory()->create();

        $this->consultant = User::factory()->create();
        $this->consultant->assignRole('consultant');
        $this->consultant->clients()->attach($this->entreprise->id);

        $this->clientAdmin = User::factory()->create();
        $this->clientAdmin->assignRole('client_admin');
        $this->clientAdmin->clients()->attach($this->entreprise->id, ['role_projet' => 'titulaire']);

        $this->autreClient = User::factory()->create();
        $this->autreClient->assignRole('client_admin');
        $this->autreClient->clients()->attach(Client::factory()->create()->id);
    }

    public function test_consultant_peut_creer_plan_avec_items(): void
    {
        $payload = [
            'client_id' => $this->entreprise->id,
            'titre' => 'Plan conformite 2026',
            'objectif' => 'Corriger les ecarts critiques',
            'items' => [
                ['titre' => 'Rediger politique de confidentialite', 'priorite' => 'p1'],
                ['titre' => 'Former les salaries', 'priorite' => 'p2'],
            ],
        ];

        $response = $this->actingAs($this->consultant)->postJson('/api/plans-actions', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('plans_actions', [
            'client_id' => $this->entreprise->id,
            'titre' => 'Plan conformite 2026',
            'statut' => 'propose',
            'propose_par' => $this->consultant->id,
        ]);
        $this->assertEquals(2, PlanActionItem::count());
    }

    public function test_client_ne_peut_pas_creer_un_plan(): void
    {
        $response = $this->actingAs($this->clientAdmin)->postJson('/api/plans-actions', [
            'client_id' => $this->entreprise->id,
            'titre' => 'Tentative',
        ]);

        $response->assertForbidden();
    }

    public function test_client_admin_peut_accepter_un_plan_propose(): void
    {
        $plan = PlanAction::factory()->create([
            'client_id' => $this->entreprise->id,
            'propose_par' => $this->consultant->id,
            'statut' => 'propose',
        ]);

        $response = $this->actingAs($this->clientAdmin)->postJson("/api/plans-actions/{$plan->id}/accepter");

        $response->assertOk();
        $this->assertEquals('accepte_client', $plan->fresh()->statut);
        $this->assertNotNull($plan->fresh()->accepte_le);
        $this->assertEquals($this->clientAdmin->id, $plan->fresh()->accepte_par);
    }

    public function test_client_autre_ne_peut_pas_accepter_le_plan(): void
    {
        $plan = PlanAction::factory()->create([
            'client_id' => $this->entreprise->id,
            'propose_par' => $this->consultant->id,
        ]);

        $response = $this->actingAs($this->autreClient)->postJson("/api/plans-actions/{$plan->id}/accepter");

        $response->assertForbidden();
    }

    public function test_client_peut_mettre_a_jour_statut_dun_item(): void
    {
        $plan = PlanAction::factory()->accepte()->create([
            'client_id' => $this->entreprise->id,
            'propose_par' => $this->consultant->id,
        ]);

        $item = PlanActionItem::create([
            'plan_action_id' => $plan->id,
            'titre' => 'Action test',
            'statut' => 'a_faire',
            'priorite' => 'p2',
            'position' => 0,
        ]);

        $response = $this->actingAs($this->clientAdmin)->putJson("/api/plans-actions/{$plan->id}/items/{$item->id}", [
            'statut' => 'termine',
            'notes_client' => 'Action realisee le 15/04',
        ]);

        $response->assertOk();
        $item = $item->fresh();
        $this->assertEquals('termine', $item->statut);
        $this->assertNotNull($item->termine_le);
        $this->assertEquals('Action realisee le 15/04', $item->notes_client);
    }

    public function test_plan_passe_en_cours_quand_premier_item_termine(): void
    {
        $plan = PlanAction::factory()->accepte()->create([
            'client_id' => $this->entreprise->id,
            'propose_par' => $this->consultant->id,
        ]);

        $item = PlanActionItem::create([
            'plan_action_id' => $plan->id,
            'titre' => 'Action test',
            'statut' => 'a_faire',
            'priorite' => 'p2',
            'position' => 0,
        ]);

        $this->actingAs($this->clientAdmin)->putJson("/api/plans-actions/{$plan->id}/items/{$item->id}", [
            'statut' => 'termine',
        ]);

        $this->assertEquals('en_cours', $plan->fresh()->statut);
    }

    public function test_consultant_peut_cloturer_un_plan(): void
    {
        $plan = PlanAction::factory()->accepte()->create([
            'client_id' => $this->entreprise->id,
            'propose_par' => $this->consultant->id,
        ]);

        $response = $this->actingAs($this->consultant)->postJson("/api/plans-actions/{$plan->id}/cloturer", [
            'commentaire' => 'Mission accomplie',
        ]);

        $response->assertOk();
        $this->assertEquals('cloture', $plan->fresh()->statut);
        $this->assertNotNull($plan->fresh()->cloture_le);
    }

    public function test_plan_cloture_nest_plus_modifiable(): void
    {
        $plan = PlanAction::factory()->cloture()->create([
            'client_id' => $this->entreprise->id,
            'propose_par' => $this->consultant->id,
        ]);

        $response = $this->actingAs($this->consultant)->putJson("/api/plans-actions/{$plan->id}", [
            'titre' => 'Nouveau titre',
        ]);

        $response->assertForbidden();
    }

    public function test_list_plans_est_scope_par_client(): void
    {
        PlanAction::factory()->count(2)->create(['client_id' => $this->entreprise->id]);
        PlanAction::factory()->count(3)->create(['client_id' => Client::factory()->create()->id]);

        $response = $this->actingAs($this->clientAdmin)->getJson('/api/plans-actions');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }
}
