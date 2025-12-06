<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use App\Models\User;
use App\Models\Role;
use Carbon\Carbon;

class SessionSecurityFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $role;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test role
        $this->role = Role::create([
            'name' => 'Test Role',
            'description' => 'Test role for session security tests'
        ]);

        // Create test user with PBKDF2 hashed password
        $hasher = app(\App\Services\PBKDF2PasswordHasher::class);
        $hashedData = $hasher->hash('password123');
        
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password_hash' => $hashedData['hash'],
            'salt' => $hashedData['salt'],
            'pbkdf2_iterations' => $hashedData['iterations'],
            'role_id' => $this->role->id,
            'password_changed_at' => Carbon::now(),
        ]);
    }

    /** @test */
    public function session_is_regenerated_on_login()
    {
        // Start a session
        $this->get('/login');
        $oldSessionId = Session::getId();
        
        // Login
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        
        $response->assertRedirect('/dashboard');
        
        // Session ID should be different after login
        $newSessionId = Session::getId();
        $this->assertNotEquals($oldSessionId, $newSessionId);
    }

    /** @test */
    public function session_fingerprint_is_initialized_on_login()
    {
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        
        $response->assertRedirect('/dashboard');
        
        // Check that session fingerprint is set
        $this->assertNotNull(Session::get('session_ip'));
        $this->assertNotNull(Session::get('session_user_agent'));
        $this->assertNotNull(Session::get('session_start_time'));
        $this->assertTrue(Session::get('session_fingerprint_initialized'));
    }

    /** @test */
    public function session_is_invalidated_on_logout()
    {
        // Login first
        $this->actingAs($this->user);
        
        $oldSessionId = Session::getId();
        
        // Logout
        $response = $this->post('/logout');
        
        $response->assertRedirect('/');
        
        // Session should be invalidated
        $this->assertGuest();
    }

    /** @test */
    public function authenticated_requests_maintain_session_fingerprint()
    {
        // Login
        $this->actingAs($this->user);
        
        // Set session fingerprint
        Session::put('session_ip', '127.0.0.1');
        Session::put('session_user_agent', $this->defaultUserAgent());
        Session::put('session_fingerprint_initialized', true);
        
        // Make authenticated request
        $response = $this->get('/dashboard');
        
        $response->assertStatus(200);
        
        // Fingerprint should still be present
        $this->assertEquals('127.0.0.1', Session::get('session_ip'));
        $this->assertEquals($this->defaultUserAgent(), Session::get('session_user_agent'));
    }

    /** @test */
    public function database_session_driver_is_configured()
    {
        $this->assertEquals('database', config('session.driver'));
    }

    /** @test */
    public function session_encryption_is_enabled()
    {
        $this->assertTrue(config('session.encrypt'));
    }

    /** @test */
    public function secure_cookie_attributes_are_configured()
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertEquals('strict', config('session.same_site'));
    }

    /**
     * Get the default user agent for tests
     */
    protected function defaultUserAgent()
    {
        return 'Symfony';
    }
}
