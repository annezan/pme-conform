<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_avec_identifiants_valides(): void
    {
        User::factory()->create([
            'email' => 'test@asc-ia.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Utiliser withSession pour simuler un contexte SPA avec session
        $response = $this->withSession([])->postJson('/api/login', [
            'email' => 'test@asc-ia.local',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user', 'message']);
    }

    public function test_login_avec_identifiants_invalides(): void
    {
        $response = $this->withSession([])->postJson('/api/login', [
            'email' => 'inexistant@test.com',
            'password' => 'mauvais',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_compte_desactive_refuse(): void
    {
        User::factory()->create([
            'email' => 'inactif@test.com',
            'password' => bcrypt('password'),
            'is_active' => false,
        ]);

        $response = $this->withSession([])->postJson('/api/login', [
            'email' => 'inactif@test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403);
    }

    public function test_route_protegee_requiert_authentification(): void
    {
        $response = $this->getJson('/api/user');
        $response->assertUnauthorized();
    }

    public function test_user_retourne_profil_connecte(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/user');

        $response->assertOk()
            ->assertJsonStructure(['user' => ['id', 'nom', 'prenom', 'email']]);
    }
}
