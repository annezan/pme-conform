<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $consultant;
    protected User $clientAdmin;
    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer les rôles et permissions
        $this->artisan('db:seed', ['class' => 'RolesAndPermissionsSeeder']);

        // Créer les utilisateurs avec rôles
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');

        $this->consultant = User::factory()->create();
        $this->consultant->assignRole('consultant');

        $this->clientAdmin = User::factory()->create();
        $this->clientAdmin->assignRole('client_admin');

        $this->client = User::factory()->create();
        $this->client->assignRole('client');
    }

    /** @test */
    public function admin_can_view_all_roles()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/roles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'guard_name',
                        'description',
                        'is_active',
                        'created_at',
                        'updated_at',
                        'permissions'
                    ]
                ]
            ]);
    }

    /** @test */
    public function manager_can_view_roles()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/admin/roles');

        $response->assertStatus(200);
    }

    /** @test */
    public function consultant_cannot_view_roles()
    {
        Sanctum::actingAs($this->consultant);

        $response = $this->getJson('/api/admin/roles');

        $response->assertStatus(403);
    }

    /** @test */
    public function client_admin_cannot_view_roles()
    {
        Sanctum::actingAs($this->clientAdmin);

        $response = $this->getJson('/api/admin/roles');

        $response->assertStatus(403);
    }

    /** @test */
    public function client_cannot_view_roles()
    {
        Sanctum::actingAs($this->client);

        $response = $this->getJson('/api/admin/roles');

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_create_role()
    {
        Sanctum::actingAs($this->admin);

        $data = [
            'name' => 'test_role',
            'description' => 'Rôle de test',
            'is_active' => true,
            'permissions' => ['view-dashboard', 'view-clients']
        ];

        $response = $this->postJson('/api/admin/roles', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'guard_name',
                    'description',
                    'is_active',
                    'created_at',
                    'updated_at'
                ]
            ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'test_role',
            'description' => 'Rôle de test',
            'is_active' => true
        ]);
    }

    /** @test */
    public function manager_cannot_create_role()
    {
        Sanctum::actingAs($this->manager);

        $data = [
            'name' => 'manager_test_role',
            'description' => 'Rôle de test manager',
            'is_active' => true,
            'permissions' => ['view-dashboard']
        ];

        $response = $this->postJson('/api/admin/roles', $data);

        $response->assertStatus(403);
    }

    /** @test */
    public function role_name_must_be_unique()
    {
        Sanctum::actingAs($this->admin);

        // Créer un premier rôle
        $role1 = Role::create(['name' => 'existing_role']);

        // Tenter de créer un deuxième avec le même nom
        $data = [
            'name' => 'existing_role',
            'description' => 'Rôle dupliqué',
            'is_active' => true
        ];

        $response = $this->postJson('/api/admin/roles', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function admin_can_update_role()
    {
        Sanctum::actingAs($this->admin);

        $role = Role::create([
            'name' => 'old_role',
            'description' => 'Ancienne description',
            'is_active' => true
        ]);

        $data = [
            'name' => 'updated_role',
            'description' => 'Nouvelle description',
            'is_active' => false,
            'permissions' => ['view-dashboard', 'view-clients']
        ];

        $response = $this->putJson("/api/admin/roles/{$role->id}", $data);

        $response->assertStatus(200);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'updated_role',
            'description' => 'Nouvelle description',
            'is_active' => false
        ]);
    }

    /** @test */
    public function manager_cannot_update_role()
    {
        Sanctum::actingAs($this->manager);

        $role = Role::create(['name' => 'test_role']);

        $data = [
            'name' => 'updated_by_manager',
            'description' => 'Modifié par manager'
        ];

        $response = $this->putJson("/api/admin/roles/{$role->id}", $data);

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_delete_role()
    {
        Sanctum::actingAs($this->admin);

        $role = Role::create(['name' => 'deletable_role']);

        $response = $this->deleteJson("/api/admin/roles/{$role->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    /** @test */
    public function manager_cannot_delete_role()
    {
        Sanctum::actingAs($this->manager);

        $role = Role::create(['name' => 'protected_role']);

        $response = $this->deleteJson("/api/admin/roles/{$role->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_toggle_role_status()
    {
        Sanctum::actingAs($this->admin);

        $role = Role::create([
            'name' => 'toggle_role',
            'is_active' => true
        ]);

        $response = $this->patchJson("/api/admin/roles/{$role->id}/toggle-actif");

        $response->assertStatus(200);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'is_active' => false
        ]);
    }

    /** @test */
    public function admin_can_view_permissions()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/permissions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'guard_name',
                        'description',
                        'group',
                        'is_active',
                        'created_at',
                        'updated_at'
                    ]
                ]
            ]);
    }

    /** @test */
    public function manager_can_view_permissions()
    {
        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/admin/permissions');

        $response->assertStatus(200);
    }

    /** @test */
    public function consultant_cannot_view_permissions()
    {
        Sanctum::actingAs($this->consultant);

        $response = $this->getJson('/api/admin/permissions');

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_attach_permissions_to_role()
    {
        Sanctum::actingAs($this->admin);

        $role = Role::create(['name' => 'test_role_for_permissions']);
        $permission1 = Permission::create(['name' => 'test_permission_1', 'guard_name' => 'web']);
        $permission2 = Permission::create(['name' => 'test_permission_2', 'guard_name' => 'web']);

        $data = [
            'permissions' => [$permission1->id, $permission2->id]
        ];

        $response = $this->postJson("/api/admin/roles/{$role->id}/permissions", $data);

        $response->assertStatus(200);

        $this->assertTrue($role->permissions()->whereIn('permissions.id', [$permission1->id, $permission2->id])->exists());
    }

    /** @test */
    public function admin_can_detach_permissions_from_role()
    {
        Sanctum::actingAs($this->admin);

        $role = Role::create(['name' => 'test_role_detach']);
        $permission1 = Permission::create(['name' => 'test_permission_detach_1', 'guard_name' => 'web']);
        $permission2 = Permission::create(['name' => 'test_permission_detach_2', 'guard_name' => 'web']);

        // Attacher les permissions
        $role->permissions()->attach([$permission1->id, $permission2->id]);

        $data = [
            'permissions' => [$permission1->id] // On garde seulement la permission1
        ];

        $response = $this->putJson("/api/admin/roles/{$role->id}/permissions", $data);

        $response->assertStatus(200);

        // Vérifier que seule la permission1 reste attachée
        $this->assertTrue($role->permissions()->where('permissions.id', $permission1->id)->exists());
        $this->assertFalse($role->permissions()->where('permissions.id', $permission2->id)->exists());
    }

    /** @test */
    public function admin_can_create_user_with_role()
    {
        Sanctum::actingAs($this->admin);

        $data = [
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'role' => 'consultant'
        ];

        $response = $this->postJson('/api/admin/users', $data);

        $response->assertStatus(201);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertTrue($user->hasRole('consultant'));
    }

    /** @test */
    public function admin_can_update_user_role()
    {
        Sanctum::actingAs($this->admin);

        $user = User::factory()->create();
        $user->assignRole('consultant');

        $data = [
            'role' => 'manager'
        ];

        $response = $this->putJson("/api/admin/users/{$user->id}", $data);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertFalse($user->hasRole('consultant'));
        $this->assertTrue($user->hasRole('manager'));
    }

    /** @test */
    public function manager_cannot_create_user_with_admin_role()
    {
        Sanctum::actingAs($this->manager);

        $data = [
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'role' => 'admin'
        ];

        $response = $this->postJson('/api/admin/users', $data);

        $response->assertStatus(403);
    }

    /** @test */
    public function can_search_roles()
    {
        Sanctum::actingAs($this->admin);

        // Créer des rôles de test
        Role::create(['name' => 'test_search_role_1', 'description' => 'Description 1']);
        Role::create(['name' => 'test_search_role_2', 'description' => 'Description 2']);

        $response = $this->getJson('/api/admin/roles?search=search');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function can_filter_permissions_by_group()
    {
        Sanctum::actingAs($this->admin);

        // Créer des permissions de test
        Permission::create(['name' => 'test_auth_permission', 'group' => 'auth', 'guard_name' => 'web']);
        Permission::create(['name' => 'test_admin_permission', 'group' => 'admin', 'guard_name' => 'web']);

        $response = $this->getJson('/api/admin/permissions?group=auth');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.group', 'auth');
    }

    /** @test */
    public function can_filter_roles_by_status()
    {
        Sanctum::actingAs($this->admin);

        // Créer des rôles avec différents statuts
        Role::create(['name' => 'active_role', 'is_active' => true]);
        Role::create(['name' => 'inactive_role', 'is_active' => false]);

        $response = $this->getJson('/api/admin/roles?is_active=false');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'inactive_role')
            ->assertJsonPath('data.0.is_active', false);
    }

    /** @test */
    public function role_permissions_are_loaded_in_response()
    {
        Sanctum::actingAs($this->admin);

        $role = Role::create(['name' => 'role_with_permissions']);
        $permission = Permission::create(['name' => 'test_permission', 'guard_name' => 'web']);
        $role->permissions()->attach($permission->id);

        $response = $this->getJson('/api/admin/roles');

        $response->assertStatus(200)
            ->assertJsonPath('data.*.permissions', function ($permissions) use ($permission) {
                $permissionIds = collect($permissions)->pluck('id');
                return $permissionIds->contains($permission->id);
            });
    }

    /** @test */
    public function user_roles_are_loaded_in_user_response()
    {
        Sanctum::actingAs($this->admin);

        $user = User::factory()->create();
        $user->assignRole('consultant');

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(200)
            ->assertJsonPath('data.*.roles', function ($roles) {
                $roleNames = collect($roles)->pluck('name');
                return $roleNames->contains('consultant');
            });
    }
}
