<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_checks_if_user_has_specific_role()
    {
        $role = Role::factory()->create(['name' => 'Administrateur']);
        $user = User::factory()->create(['role_id' => $role->id]);
        
        $this->assertTrue($user->hasRole('Administrateur'));
        $this->assertFalse($user->hasRole('Manager'));
    }

    /** @test */
    public function it_returns_false_when_user_has_no_role()
    {
        $user = User::factory()->create(['role_id' => null]);
        
        $this->assertFalse($user->hasRole('Administrateur'));
    }

    /** @test */
    public function it_checks_if_user_has_any_of_specified_roles()
    {
        $role = Role::factory()->create(['name' => 'Administrateur']);
        $user = User::factory()->create(['role_id' => $role->id]);
        
        $this->assertTrue($user->hasAnyRole(['Administrateur', 'Manager']));
        $this->assertFalse($user->hasAnyRole(['Manager', 'User']));
    }

    /** @test */
    public function it_checks_if_user_has_permission()
    {
        $permission = Permission::factory()->create(['name' => 'view_dashboard']);
        $role = Role::factory()->create(['name' => 'Administrateur']);
        $role->permissions()->attach($permission);
        
        $user = User::factory()->create(['role_id' => $role->id]);
        
        $this->assertTrue($user->hasPermission('view_dashboard'));
        $this->assertFalse($user->hasPermission('nonexistent_permission'));
    }

    /** @test */
    public function it_checks_resource_access()
    {
        $permission = Permission::factory()->create([
            'name' => 'view_clients',
            'resource' => 'clients',
            'action' => 'view'
        ]);
        $role = Role::factory()->create(['name' => 'Administrateur']);
        $role->permissions()->attach($permission);
        
        $user = User::factory()->create(['role_id' => $role->id]);
        
        $this->assertTrue($user->canAccess('clients', 'view'));
        $this->assertFalse($user->canAccess('clients', 'delete'));
    }

    /** @test */
    public function it_returns_user_permissions()
    {
        $permission1 = Permission::factory()->create(['name' => 'view_dashboard']);
        $permission2 = Permission::factory()->create(['name' => 'manage_users']);
        
        $role = Role::factory()->create(['name' => 'Administrateur']);
        $role->permissions()->attach([$permission1->id, $permission2->id]);
        
        $user = User::factory()->create(['role_id' => $role->id]);
        
        $permissions = $user->getPermissions();
        
        $this->assertCount(2, $permissions);
        $this->assertTrue($permissions->contains('name', 'view_dashboard'));
        $this->assertTrue($permissions->contains('name', 'manage_users'));
    }

    /** @test */
    public function it_returns_empty_collection_when_user_has_no_role()
    {
        $user = User::factory()->create(['role_id' => null]);
        
        $permissions = $user->getPermissions();
        
        $this->assertTrue($permissions->isEmpty());
    }
}