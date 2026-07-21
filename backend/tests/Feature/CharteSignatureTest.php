<?php

namespace Tests\Feature;

use App\Models\Charte;
use App\Models\Client;
use App\Models\Signature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharteSignatureTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private Client $entreprise;
    private Charte $charte;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->entreprise = Client::factory()->create();
        $this->client = User::factory()->create();
        $this->client->assignRole('client_admin');
        $this->client->clients()->attach($this->entreprise->id);

        $this->charte = Charte::factory()->create();
    }

    public function test_un_utilisateur_peut_signer_une_charte(): void
    {
        $response = $this->actingAs($this->client)->postJson("/api/chartes/{$this->charte->id}/signer", [
            'accepte_contenu' => true,
            'hash_affiche' => $this->charte->hash_contenu,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('signatures', [
            'charte_id' => $this->charte->id,
            'user_id' => $this->client->id,
            'client_id' => $this->entreprise->id,
            'statut' => 'signee',
        ]);
    }

    public function test_signature_refusee_si_hash_modifie(): void
    {
        $response = $this->actingAs($this->client)->postJson("/api/chartes/{$this->charte->id}/signer", [
            'accepte_contenu' => true,
            'hash_affiche' => str_repeat('a', 64), // hash different
        ]);

        $response->assertStatus(409);
        $this->assertDatabaseCount('signatures', 0);
    }

    public function test_signature_trace_ip_et_user_agent(): void
    {
        $this->actingAs($this->client)
            ->withHeaders(['User-Agent' => 'TestBrowser/1.0'])
            ->postJson("/api/chartes/{$this->charte->id}/signer", [
                'accepte_contenu' => true,
                'hash_affiche' => $this->charte->hash_contenu,
            ]);

        $signature = Signature::first();
        $this->assertNotEmpty($signature->ip_signature);
        $this->assertStringContainsString('TestBrowser', $signature->user_agent_signature);
        $this->assertEquals($this->charte->hash_contenu, $signature->hash_contenu_signe);
    }

    public function test_re_signer_revoque_la_precedente(): void
    {
        // Premiere signature
        $this->actingAs($this->client)->postJson("/api/chartes/{$this->charte->id}/signer", [
            'accepte_contenu' => true,
            'hash_affiche' => $this->charte->hash_contenu,
        ]);

        // Deuxieme signature
        $this->actingAs($this->client)->postJson("/api/chartes/{$this->charte->id}/signer", [
            'accepte_contenu' => true,
            'hash_affiche' => $this->charte->hash_contenu,
        ]);

        // 2 signatures au total : 1 revoquee + 1 active
        $this->assertEquals(1, Signature::where('statut', 'signee')->count());
        $this->assertEquals(1, Signature::where('statut', 'revoquee')->count());
    }

    public function test_utilisateur_peut_revoquer_sa_signature(): void
    {
        $this->actingAs($this->client)->postJson("/api/chartes/{$this->charte->id}/signer", [
            'accepte_contenu' => true,
            'hash_affiche' => $this->charte->hash_contenu,
        ]);

        $signature = Signature::first();
        $response = $this->actingAs($this->client)->postJson("/api/signatures/{$signature->id}/revoquer", [
            'raison' => 'Test de revocation',
        ]);

        $response->assertOk();
        $this->assertEquals('revoquee', $signature->fresh()->statut);
    }

    public function test_ne_peut_pas_revoquer_signature_dautrui(): void
    {
        $autreUser = User::factory()->create();
        $autreUser->assignRole('client_admin');
        $autreUser->clients()->attach(Client::factory()->create()->id);

        $this->actingAs($this->client)->postJson("/api/chartes/{$this->charte->id}/signer", [
            'accepte_contenu' => true,
            'hash_affiche' => $this->charte->hash_contenu,
        ]);
        $signature = Signature::first();

        $response = $this->actingAs($autreUser)->postJson("/api/signatures/{$signature->id}/revoquer");

        $response->assertForbidden();
    }

    public function test_liste_chartes_indique_statut_signee_correctement(): void
    {
        // Non signee
        $response = $this->actingAs($this->client)->getJson('/api/chartes');
        $charteData = collect($response->json('data'))->firstWhere('id', $this->charte->id);
        $this->assertFalse($charteData['signee']);

        // Apres signature
        $this->actingAs($this->client)->postJson("/api/chartes/{$this->charte->id}/signer", [
            'accepte_contenu' => true,
            'hash_affiche' => $this->charte->hash_contenu,
        ]);

        $response = $this->actingAs($this->client)->getJson('/api/chartes');
        $charteData = collect($response->json('data'))->firstWhere('id', $this->charte->id);
        $this->assertTrue($charteData['signee']);
        $this->assertTrue($charteData['signature_valide']);
    }
}
