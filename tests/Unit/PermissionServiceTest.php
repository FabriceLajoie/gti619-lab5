<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PermissionService;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $permissionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->permissionService = new PermissionService();
    }

    /** @test */
    public function it_returns_false_when_user_has_no_role()
    {
        $user = User::factory()->create(['role_id' => null]);
        
        $result = $this->permissionService->userHasPermission($user, 'view_dashboard');
        
        $this->assertFalse($result);
    }

    /** @test */
    public function it_checks_user_permission_correctly()
    {
        $permission = Permission::factory()->create(['name' => 'view_dashboard']);
        $role = Role::factory()->create(['name' => 'Administrateur']);
        $role->permissions()->attach($permission);
        
        $user = User::factory()->create(['role_id' => $role->id]);
        
        $result = $this->permissionService->userHasPermission($user, 'view_dashboard');
        
        $this->assertTrue($result);
    }

    /** @test */
    public function it_checks_user_permission_for_resource_and_action()
    {
        $permission = Permission::factory()->create([
            'name' => 'view_clients',
            'resource' => 'clients',
            'action' => 'view'
        ]);
        $role = Role::factory()->create(['name' => 'Administrateur']);
        $role->permissions()->attach($permission);
        
        $user = User::factory()->create(['role_id' => $role->id]);
        
        $result = $this->permissionService->userHasPermissionFor($user, 'clients', 'view');
        
        $this->assertTrue($result);
    }

    /** @test */
    public function it_checks_if_user_has_any_of_specified_roles()
    {
        $role = Role::factory()->create(['name' => 'Administrateur']);
        $user = User::factory()->create(['role_id' => $role->id]);
        
        $result = $this->permissionService->userHasAnyRole($user, ['Administrateur', 'Manager']);
        
        $this->assertTrue($result);
        
        $result = $this->permissionService->userHasAnyRole($user, ['Manager', 'User']);
        
        $this->assertFalse($result);
    }

    /** @test */
    public function it_returns_user_permissions()
    {
        $permission1 = Permission::factory()->create(['name' => 'view_dashboard']);
        $permission2 = Permission::factory()->create(['name' => 'manage_users']);
        
        $role = Role::factory()->create(['name' => 'Administrateur']);
        $role->permissions()->attach([$permission1->id, $permission2->id]);
        
        $user = User::factory()->create(['role_id' => $role->id]);
        
        $permissions = $this->permissionService->getUserPermissions($user);
        
        $this->assertCount(2, $permissions);
        $this->assertTrue($permissions->contains('name', 'view_dashboard'));
        $this->assertTrue($permissions->contains('name', 'manage_users'));
    }

    /** @test */
    public function it_returns_empty_collection_for_user_without_role()
    {
        $user = User::factory()->create(['role_id' => null]);
        
        $permissions = $this->permissionService->getUserPermissions($user);
        
        $this->assertTrue($permissions->isEmpty());
    }

    /** @test */
    public function it_returns_default_role_permissions()
    {
        $defaultPermissions = $this->permissionService->getDefaultRolePermissions();
        
        $this->assertIsArray($defaultPermissions);
        $this->assertArrayHasKey('Administrateur', $defaultPermissions);
        $this->assertArrayHasKey('Préposé aux clients résidentiels', $defaultPermissions);
        $this->assertArrayHasKey('Préposé aux clients d\'affaire', $defaultPermissions);
        
        // Check that Administrateur has the most permissions
        $this->assertGreaterThan(
            count($defaultPermissions['Préposé aux clients résidentiels']),
            count($defaultPermissions['Administrateur'])
        );
    }

    /** @test */
    public function it_checks_route_access_correctly()
    {
        $permission = Permission::factory()->create(['name' => 'view_dashboard']);
        $role = Role::factory()->create(['name' => 'Administrateur']);
        $role->permissions()->attach($permission);
        
        $user = User::factory()->create(['role_id' => $role->id]);
        
        $result = $this->permissionService->canAccessRoute($user, 'dashboard');
        
        $this->assertTrue($result);
    }

    /** @test */
    public function it_allows_access_to_undefined_routes_for_backward_compatibility()
    {
        $role = Role::factory()->create(['name' => 'Administrateur']);
        $user = User::factory()->create(['role_id' => $role->id]);
        
        $result = $this->permissionService->canAccessRoute($user, 'undefined.route');
        
        $this->assertTrue($result);
    }

    /** @test */
    public function it_denies_route_access_when_user_lacks_permission()
    {
        $role = Role::factory()->create(['name' => 'Préposé aux clients résidentiels']);
        $user = User::factory()->create(['role_id' => $role->id]);
        
        $result = $this->permissionService->canAccessRoute($user, 'settings');
        
        $this->assertFalse($result);
    }
}