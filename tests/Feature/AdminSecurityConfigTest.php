<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\SecurityConfig;
use App\Services\SecurityConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSecurityConfigTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $adminRole = Role::create(['name' => 'Administrateur', 'description' => 'System administrator']);
        $userRole = Role::create(['name' => 'Préposé aux clients résidentiels', 'description' => 'Residential clerk']);

        // Create users
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $adminRole->id,
        ]);

        $this->regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
            'role_id' => $userRole->id,
        ]);

        // Create default security config
        SecurityConfig::create([]);
    }

    public function test_admin_can_access_security_config_page()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.security-config'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.security-config');
        $response->assertViewHas('config');
    }

    public function test_regular_user_cannot_access_security_config_page()
    {
        $response = $this->actingAs($this->regularUser)
            ->get(route('admin.security-config'));

        $response->assertStatus(302);
        $response->assertRedirect(route('clients.residential'));
    }

    public function test_admin_can_update_security_config()
    {
        $updateData = [
            'max_login_attempts' => 3,
            'lockout_duration_minutes' => 60,
            'password_min_length' => 10,
            'password_require_uppercase' => true,
            'password_require_lowercase' => true,
            'password_require_numbers' => false,
            'password_require_special' => false,
            'password_history_count' => 3,
            'password_expiry_days' => 60,
            'pbkdf2_iterations' => 150000,
            'session_timeout_minutes' => 90
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.security-config.update'), $updateData);

        $response->assertRedirect(route('admin.security-config'));
        $response->assertSessionHas('success');

        // Verify the config was updated
        $config = SecurityConfig::getInstance();
        $this->assertEquals(3, $config->max_login_attempts);
        $this->assertEquals(60, $config->lockout_duration_minutes);
        $this->assertEquals(10, $config->password_min_length);
        $this->assertFalse($config->password_require_numbers);
        $this->assertFalse($config->password_require_special);
    }

    public function test_admin_can_reset_security_config()
    {
        // First update config to non-default values
        $config = SecurityConfig::getInstance();
        $config->update([
            'max_login_attempts' => 10,
            'password_min_length' => 20,
        ]);

        // Reset to defaults
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.security-config.reset'));

        $response->assertRedirect(route('admin.security-config'));
        $response->assertSessionHas('success');

        // Verify config was reset to defaults
        $config = $config->fresh();
        $this->assertEquals(5, $config->max_login_attempts);
        $this->assertEquals(12, $config->password_min_length);
    }

    public function test_security_config_validation()
    {
        $invalidData = [
            'max_login_attempts' => 0, // Too low
            'password_min_length' => 200, // Too high
            'pbkdf2_iterations' => 5000, // Too low
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.security-config.update'), $invalidData);

        $response->assertRedirect(route('admin.security-config'));
        $response->assertSessionHasErrors(['max_login_attempts', 'password_min_length', 'pbkdf2_iterations']);
    }

    public function test_admin_can_access_users_page()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.users'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.users');
        $response->assertViewHas('users');
        $response->assertViewHas('roles');
    }

    public function test_admin_can_view_user_details()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.users.details', $this->regularUser));

        $response->assertStatus(200);
        $response->assertViewIs('admin.user-details');
        $response->assertViewHas('user');
        $response->assertViewHas('recentLogs');
        $response->assertViewHas('stats');
    }

    public function test_admin_can_unlock_locked_user()
    {
        // Lock the user
        $this->regularUser->update([
            'locked_until' => now()->addHours(1),
            'failed_login_attempts' => 5,
        ]);

        $this->assertTrue($this->regularUser->fresh()->isLocked());

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.users.unlock', $this->regularUser));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify user is unlocked
        $user = $this->regularUser->fresh();
        $this->assertFalse($user->isLocked());
        $this->assertEquals(0, $user->failed_login_attempts);
        $this->assertNull($user->locked_until);
    }

    public function test_admin_cannot_unlock_already_unlocked_user()
    {
        $this->assertFalse($this->regularUser->isLocked());

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.users.unlock', $this->regularUser));

        $response->assertRedirect();
        $response->assertSessionHas('warning');
    }
}