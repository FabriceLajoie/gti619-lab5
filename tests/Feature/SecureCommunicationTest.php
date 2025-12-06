<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SecureCommunicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        Role::create(['name' => 'Administrateur']);
        Role::create(['name' => 'Préposé aux clients résidentiels']);
        Role::create(['name' => 'Préposé aux clients d\'affaire']);
    }

    /** @test */
    public function it_adds_security_headers_to_responses()
    {
        $response = $this->get('/login');

        // Check for security headers
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Check for CSP header
        $this->assertTrue($response->headers->has('Content-Security-Policy'));
        $this->assertStringContainsString("default-src 'self'", $response->headers->get('Content-Security-Policy'));
        
        // Check for Permissions Policy
        $this->assertTrue($response->headers->has('Permissions-Policy'));
    }

    /** @test */
    public function it_adds_hsts_header_to_responses()
    {
        $response = $this->get('/login');

        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    /** @test */
    public function it_protects_login_with_csrf()
    {
        // Attempt login without CSRF token should fail
        $response = $this->post('/login', [
            'username' => 'testuser',
            'password' => 'password123',
        ]);

        $response->assertStatus(419); // CSRF token mismatch
    }

    /** @test */
    public function it_protects_logout_with_csrf()
    {
        $adminRole = Role::where('name', 'Administrateur')->first();
        $user = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        $this->actingAs($user);

        // Attempt logout without CSRF token should fail
        $response = $this->post('/logout');

        $response->assertStatus(419); // CSRF token mismatch
    }

    /** @test */
    public function it_protects_admin_security_config_update_with_csrf()
    {
        $adminRole = Role::where('name', 'Administrateur')->first();
        $user = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        $this->actingAs($user);

        // Attempt to update security config without CSRF token should fail
        $response = $this->post('/admin/security-config', [
            'max_login_attempts' => 5,
        ]);

        $response->assertStatus(419); // CSRF token mismatch
    }

    /** @test */
    public function it_protects_user_creation_with_csrf()
    {
        $adminRole = Role::where('name', 'Administrateur')->first();
        $user = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        $this->actingAs($user);

        // Attempt to create user without CSRF token should fail
        $response = $this->post('/admin/users', [
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => 'SecurePass123!',
        ]);

        $response->assertStatus(419); // CSRF token mismatch
    }

    /** @test */
    public function it_protects_password_change_with_csrf()
    {
        $adminRole = Role::where('name', 'Administrateur')->first();
        $user = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        $this->actingAs($user);

        // Attempt to change password without CSRF token should fail
        $response = $this->post('/password/change', [
            'current_password' => 'oldpassword',
            'password' => 'NewSecurePass123!',
            'password_confirmation' => 'NewSecurePass123!',
        ]);

        $response->assertStatus(419); // CSRF token mismatch
    }

    /** @test */
    public function session_cookies_have_secure_attributes()
    {
        // Session configuration should have secure settings
        $this->assertTrue(config('session.http_only'), 'Session cookies should be HTTP only');
        $this->assertEquals('strict', config('session.same_site'), 'Session cookies should use SameSite=Strict');
        $this->assertTrue(config('session.encrypt'), 'Session data should be encrypted');
    }

    /** @test */
    public function it_uses_database_session_driver()
    {
        // Verify session driver is set to database for secure server-side storage
        $this->assertEquals('database', config('session.driver'));
    }

    /** @test */
    public function https_enforcement_respects_environment()
    {
        // In local/testing environment, HTTPS should not be enforced by default
        config(['app.env' => 'local']);
        config(['app.force_https' => false]);
        
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    /** @test */
    public function it_adds_security_headers_to_authenticated_routes()
    {
        $adminRole = Role::where('name', 'Administrateur')->first();
        $user = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        // Check for security headers on authenticated routes
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    /** @test */
    public function it_adds_security_headers_to_admin_routes()
    {
        $adminRole = Role::where('name', 'Administrateur')->first();
        $user = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        $this->actingAs($user);

        $response = $this->get('/admin/security-config');

        // Check for security headers on admin routes
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    /** @test */
    public function csrf_protection_allows_valid_requests_with_token()
    {
        $adminRole = Role::where('name', 'Administrateur')->first();
        $user = User::factory()->create([
            'role_id' => $adminRole->id,
            'username' => 'testadmin',
        ]);

        // Login with CSRF token (Laravel test helpers automatically include it)
        $response = $this->post('/login', [
            'username' => 'testadmin',
            'password' => 'password',
        ]);

        // Should not get CSRF error
        $this->assertNotEquals(419, $response->status());
    }
}
