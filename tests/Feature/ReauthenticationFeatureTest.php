<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Services\PBKDF2PasswordHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class ReauthenticationFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $adminRole;
    protected $passwordHasher;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->passwordHasher = app(PBKDF2PasswordHasher::class);
        
        // Create admin role
        $this->adminRole = Role::create([
            'name' => 'Administrateur',
            'description' => 'Administrator role'
        ]);

        // Create test user with PBKDF2 password
        $hashedData = $this->passwordHasher->hash('password123');
        $this->user = User::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password_hash' => $hashedData['hash'],
            'salt' => $hashedData['salt'],
            'pbkdf2_iterations' => $hashedData['iterations'],
            'role_id' => $this->adminRole->id,
        ]);
    }

    /** @test */
    public function it_shows_reauth_form_when_accessing_protected_route()
    {
        $this->actingAs($this->user);

        $response = $this->get('/admin/users/create');
        
        $response->assertRedirect('/reauth');
        $response->assertSessionHas('url.intended', 'http://localhost/admin/users/create');
    }

    /** @test */
    public function it_displays_reauth_form_correctly()
    {
        $this->actingAs($this->user);

        $response = $this->get('/reauth');
        
        $response->assertStatus(200);
        $response->assertViewIs('auth.reauth');
        $response->assertSee('Re-authentication Required');
        $response->assertSee('Current Password');
    }

    /** @test */
    public function it_validates_password_on_reauth_attempt()
    {
        $this->actingAs($this->user);

        // Test with wrong password
        $response = $this->post('/reauth', [
            'password' => 'wrongpassword'
        ]);

        $response->assertRedirect('/reauth');
        $response->assertSessionHasErrors(['password']);

        // Test with correct password
        $response = $this->post('/reauth', [
            'password' => 'password123'
        ]);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('success');
    }

    /** @test */
    public function it_redirects_to_intended_url_after_successful_reauth()
    {
        $this->actingAs($this->user);
        
        // Set intended URL
        Session::put('url.intended', '/admin/users/create');

        $response = $this->post('/reauth', [
            'password' => 'password123'
        ]);

        $response->assertRedirect('/admin/users/create');
        $this->assertFalse(Session::has('url.intended'));
    }

    /** @test */
    public function it_allows_access_to_protected_route_after_reauth()
    {
        $this->actingAs($this->user);
        
        // Set recent reauth timestamp
        Session::put('last_reauth_at', Carbon::now()->toISOString());

        $response = $this->get('/admin/users/create');
        
        $response->assertStatus(200);
        $response->assertViewIs('admin.create-user');
    }

    /** @test */
    public function password_change_requires_reauth()
    {
        $this->actingAs($this->user);

        $response = $this->get('/password/change');
        
        $response->assertRedirect('/reauth');
    }

    /** @test */
    public function password_change_works_after_reauth()
    {
        $this->actingAs($this->user);
        
        // Set recent reauth timestamp
        Session::put('last_reauth_at', Carbon::now()->toISOString());

        $response = $this->get('/password/change');
        $response->assertStatus(200);

        // Test password change
        $response = $this->post('/password/change', [
            'current_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123'
        ]);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('success');
        
        // Verify password was changed
        $this->user->refresh();
        $this->assertTrue($this->passwordHasher->verify(
            'newpassword123',
            $this->user->salt,
            $this->user->password_hash,
            $this->user->pbkdf2_iterations
        ));
    }

    /** @test */
    public function admin_user_creation_requires_reauth()
    {
        $this->actingAs($this->user);

        $response = $this->post('/admin/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $this->adminRole->id
        ]);

        $response->assertRedirect('/reauth');
    }

    /** @test */
    public function admin_user_creation_works_after_reauth()
    {
        $this->actingAs($this->user);
        
        // Set recent reauth timestamp
        Session::put('last_reauth_at', Carbon::now()->toISOString());

        $response = $this->post('/admin/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $this->adminRole->id
        ]);

        $response->assertRedirect('/admin/users');
        $response->assertSessionHas('success');
        
        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'newuser@example.com'
        ]);
    }

    /** @test */
    public function reauth_expires_after_configured_time()
    {
        $this->actingAs($this->user);
        
        // Set old reauth timestamp (20 minutes ago)
        Session::put('last_reauth_at', Carbon::now()->subMinutes(20)->toISOString());

        // Should require reauth for 15-minute protected route
        $response = $this->get('/admin/users/create');
        $response->assertRedirect('/reauth');

        // Set recent reauth timestamp (5 minutes ago)
        Session::put('last_reauth_at', Carbon::now()->subMinutes(5)->toISOString());

        // Should allow access
        $response = $this->get('/admin/users/create');
        $response->assertStatus(200);
    }

    /** @test */
    public function different_routes_can_have_different_reauth_timeouts()
    {
        $this->actingAs($this->user);
        
        // Set reauth timestamp 8 minutes ago
        Session::put('last_reauth_at', Carbon::now()->subMinutes(8)->toISOString());

        // Password change (5 minute timeout) should require reauth
        $response = $this->get('/password/change');
        $response->assertRedirect('/reauth');

        // Admin routes (10 minute timeout) should still allow access
        $response = $this->get('/admin/users');
        $response->assertStatus(200);
    }

    /** @test */
    public function reauth_is_forced_after_password_change()
    {
        $this->actingAs($this->user);
        
        // Set recent reauth timestamp
        Session::put('last_reauth_at', Carbon::now()->toISOString());

        // Change password
        $response = $this->post('/password/change', [
            'current_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123'
        ]);

        $response->assertRedirect('/dashboard');

        // Should now require reauth for protected operations
        $response = $this->get('/admin/users/create');
        $response->assertRedirect('/reauth');
    }
}