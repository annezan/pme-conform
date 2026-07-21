<?php

namespace Tests\Unit;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function permission_model_extends_spatie_permission()
    {
        $permission = new Permission();

        $this->assertInstanceOf(\Spatie\Permission\Models\Permission::class, $permission);
    }

    /** @test */
    public function role_model_extends_spatie_role()
    {
        $role = new Role();

        $this->assertInstanceOf(\Spatie\Permission\Models\Role::class, $role);
    }

    /** @test */
    public function permission_has_custom_fillable_fields()
    {
        $permission = new Permission();

        $fillable = ['name', 'guard_name', 'description', 'group', 'is_active'];

        $this->assertEquals($fillable, $permission->getFillable());
    }

    /** @test */
    public function role_has_custom_fillable_fields()
    {
        $role = new Role();

        $fillable = ['name', 'guard_name', 'description', 'is_active'];

        $this->assertEquals($fillable, $role->getFillable());
    }

    /** @test */
    public function permission_casts_is_active_to_boolean()
    {
        $permission = Permission::create([
            'name' => 'test_permission',
            'guard_name' => 'web',
            'description' => 'Test description',
            'group' => 'test',
            'is_active' => true
        ]);

        $this->assertIsBool($permission->is_active);
        $this->assertTrue($permission->is_active);

        $permission->update(['is_active' => false]);
        $this->assertFalse($permission->is_active);
    }

    /** @test */
    public function role_casts_is_active_to_boolean()
    {
        $role = Role::create([
            'name' => 'test_role',
            'guard_name' => 'web',
            'description' => 'Test description',
            'is_active' => true
        ]);

        $this->assertIsBool($role->is_active);
        $this->assertTrue($role->is_active);

        $role->update(['is_active' => false]);
        $this->assertFalse($role->is_active);
    }

    /** @test */
    public function permission_has_active_scope()
    {
        // Créer des permissions actives et inactives
        $activePermission = Permission::create([
            'name' => 'active_permission',
            'guard_name' => 'web',
            'is_active' => true
        ]);

        $inactivePermission = Permission::create([
            'name' => 'inactive_permission',
            'guard_name' => 'web',
            'is_active' => false
        ]);

        $activePermissions = Permission::active()->get();

        $this->assertCount(1, $activePermissions);
        $this->assertEquals($activePermission->id, $activePermissions->first()->id);
    }

    /** @test */
    public function role_has_active_scope()
    {
        // Créer des rôles actifs et inactifs
        $activeRole = Role::create([
            'name' => 'active_role',
            'guard_name' => 'web',
            'is_active' => true
        ]);

        $inactiveRole = Role::create([
            'name' => 'inactive_role',
            'guard_name' => 'web',
            'is_active' => false
        ]);

        $activeRoles = Role::active()->get();

        $this->assertCount(1, $activeRoles);
        $this->assertEquals($activeRole->id, $activeRoles->first()->id);
    }

    /** @test */
    public function permission_has_by_group_scope()
    {
        $permission1 = Permission::create([
            'name' => 'auth_permission',
            'guard_name' => 'web',
            'group' => 'auth'
        ]);

        $permission2 = Permission::create([
            'name' => 'admin_permission',
            'guard_name' => 'web',
            'group' => 'admin'
        ]);

        $authPermissions = Permission::byGroup('auth')->get();

        $this->assertCount(1, $authPermissions);
        $this->assertEquals($permission1->id, $authPermissions->first()->id);
    }

    /** @test */
    public function permission_has_search_scope()
    {
        $permission1 = Permission::create([
            'name' => 'view_dashboard',
            'guard_name' => 'web',
            'description' => 'Voir le dashboard principal'
        ]);

        $permission2 = Permission::create([
            'name' => 'edit_profile',
            'guard_name' => 'web',
            'description' => 'Modifier le profil utilisateur'
        ]);

        // Recherche par nom
        $results = Permission::search('dashboard')->get();
        $this->assertCount(1, $results);
        $this->assertEquals($permission1->id, $results->first()->id);

        // Recherche par description
        $results = Permission::search('profil')->get();
        $this->assertCount(1, $results);
        $this->assertEquals($permission2->id, $results->first()->id);
    }

    /** @test */
    public function role_has_search_scope()
    {
        $role1 = Role::create([
            'name' => 'super_admin',
            'guard_name' => 'web',
            'description' => 'Administrateur principal du système'
        ]);

        $role2 = Role::create([
            'name' => 'content_manager',
            'guard_name' => 'web',
            'description' => 'Gestionnaire de contenu'
        ]);

        // Recherche par nom
        $results = Role::search('admin')->get();
        $this->assertCount(1, $results);
        $this->assertEquals($role1->id, $results->first()->id);

        // Recherche par description
        $results = Role::search('contenu')->get();
        $this->assertCount(1, $results);
        $this->assertEquals($role2->id, $results->first()->id);
    }

    /** @test */
    public function role_has_permissions_relation()
    {
        $role = Role::create(['name' => 'test_role', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'test_permission', 'guard_name' => 'web']);

        $role->permissions()->attach($permission->id);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class, $role->permissions());
        $this->assertTrue($role->permissions()->exists());
    }

    /** @test */
    public function permission_has_roles_relation()
    {
        $permission = Permission::create(['name' => 'test_permission', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'test_role', 'guard_name' => 'web']);

        $permission->roles()->attach($role->id);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class, $permission->roles());
        $this->assertTrue($permission->roles()->exists());
    }

    /** @test */
    public function role_logs_activity()
    {
        $role = Role::create([
            'name' => 'test_role',
            'guard_name' => 'web',
            'description' => 'Rôle de test'
        ]);

        // Vérifier que l'activité est loggée
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => get_class($role),
            'subject_id' => $role->id,
            'description' => 'created'
        ]);
    }

    /** @test */
    public function permission_can_be_created_with_custom_fields()
    {
        $permission = Permission::create([
            'name' => 'custom_permission',
            'guard_name' => 'web',
            'description' => 'Permission personnalisée',
            'group' => 'custom_group',
            'is_active' => true
        ]);

        $this->assertEquals('custom_permission', $permission->name);
        $this->assertEquals('web', $permission->guard_name);
        $this->assertEquals('Permission personnalisée', $permission->description);
        $this->assertEquals('custom_group', $permission->group);
        $this->assertTrue($permission->is_active);
    }

    /** @test */
    public function role_can_be_created_with_custom_fields()
    {
        $role = Role::create([
            'name' => 'custom_role',
            'guard_name' => 'web',
            'description' => 'Rôle personnalisé',
            'is_active' => true
        ]);

        $this->assertEquals('custom_role', $role->name);
        $this->assertEquals('web', $role->guard_name);
        $this->assertEquals('Rôle personnalisé', $role->description);
        $this->assertTrue($role->is_active);
    }

    /** @test */
    public function permission_name_and_guard_name_must_be_unique()
    {
        $this->expectException(\Spatie\Permission\Exceptions\PermissionAlreadyExists::class);

        Permission::create(['name' => 'duplicate_permission', 'guard_name' => 'web']);
        Permission::create(['name' => 'duplicate_permission', 'guard_name' => 'web']);
    }

    /** @test */
    public function role_name_and_guard_name_must_be_unique()
    {
        $this->expectException(\Spatie\Permission\Exceptions\RoleAlreadyExists::class);

        Role::create(['name' => 'duplicate_role', 'guard_name' => 'web']);
        Role::create(['name' => 'duplicate_role', 'guard_name' => 'web']);
    }

    /** @test */
    public function user_can_have_multiple_roles()
    {
        $user = User::factory()->create();
        $role1 = Role::create(['name' => 'role1', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'role2', 'guard_name' => 'web']);

        $user->assignRole($role1);
        $user->assignRole($role2);

        $this->assertTrue($user->hasRole('role1'));
        $this->assertTrue($user->hasRole('role2'));
        $this->assertCount(2, $user->roles);
    }

    /** @test */
    public function user_can_have_direct_permissions()
    {
        $user = User::factory()->create();
        $permission = Permission::create(['name' => 'direct_permission', 'guard_name' => 'web']);

        $user->givePermissionTo($permission);

        $this->assertTrue($user->hasDirectPermission('direct_permission'));
        $this->assertDatabaseHas('model_has_permissions', [
            'model_type' => get_class($user),
            'model_id' => $user->id,
            'permission_id' => $permission->id
        ]);
    }

    /** @test */
    public function user_can_have_permissions_through_role()
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'test_role', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'role_permission', 'guard_name' => 'web']);

        $role->permissions()->attach($permission->id);
        $user->assignRole($role);

        $this->assertTrue($user->hasPermissionTo('role_permission'));
    }

    /** @test */
    public function user_can_sync_roles()
    {
        $user = User::factory()->create();
        $role1 = Role::create(['name' => 'role1', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'role2', 'guard_name' => 'web']);
        $role3 = Role::create(['name' => 'role3', 'guard_name' => 'web']);

        // Assigner les rôles 1 et 2
        $user->assignRole($role1);
        $user->assignRole($role2);

        $this->assertTrue($user->hasRole('role1'));
        $this->assertTrue($user->hasRole('role2'));

        // Synchroniser avec les rôles 2 et 3
        $user->syncRoles([$role2->name, $role3->name]);

        $this->assertFalse($user->hasRole('role1'));
        $this->assertTrue($user->hasRole('role2'));
        $this->assertTrue($user->hasRole('role3'));
    }

    /** @test */
    public function role_can_sync_permissions()
    {
        $role = Role::create(['name' => 'test_role', 'guard_name' => 'web']);
        $permission1 = Permission::create(['name' => 'permission1', 'guard_name' => 'web']);
        $permission2 = Permission::create(['name' => 'permission2', 'guard_name' => 'web']);
        $permission3 = Permission::create(['name' => 'permission3', 'guard_name' => 'web']);

        // Attacher les permissions 1 et 2
        $role->permissions()->attach([$permission1->id, $permission2->id]);

        $this->assertTrue($role->hasPermissionTo('permission1'));
        $this->assertTrue($role->hasPermissionTo('permission2'));

        // Synchroniser avec les permissions 2 et 3
        $role->permissions()->sync([$permission2->id, $permission3->id]);

        $this->assertFalse($role->hasPermissionTo('permission1'));
        $this->assertTrue($role->hasPermissionTo('permission2'));
        $this->assertTrue($role->hasPermissionTo('permission3'));
    }

    /** @test */
    public function permission_can_be_checked_by_name()
    {
        $permission = Permission::create(['name' => 'test_permission', 'guard_name' => 'web']);

        $this->assertTrue(Permission::findByName('test_permission', 'web') !== null);
        $this->assertEquals($permission->id, Permission::findByName('test_permission', 'web')->id);
    }

    /** @test */
    public function role_can_be_checked_by_name()
    {
        $role = Role::create(['name' => 'test_role', 'guard_name' => 'web']);

        $this->assertTrue(Role::findByName('test_role', 'web') !== null);
        $this->assertEquals($role->id, Role::findByName('test_role', 'web')->id);
    }
}
