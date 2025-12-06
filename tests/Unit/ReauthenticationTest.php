<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\ReauthenticationController;
use App\Http\Middleware\RequireReauthentication;
use App\Services\SessionSecurityService;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class ReauthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $adminRole;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin role
        $this->adminRole = Role::create([
            'name' => 'Administrateur',
            'description' => 'Administrator role'
        ]);

        // Create test user
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $this->adminRole->id,
        ]);
    }

    /** @test */
    public function it_detects_when_reauth_is_needed()
    {
        // No reauth timestamp should require reauth
        $this->assertTrue(ReauthenticationController::needsReauth());

        // Recent reauth should not require reauth
        Session::put('last_reauth_at', Carbon::now()->toISOString());
        $this->assertFalse(ReauthenticationController::needsReauth());

        // Old reauth should require reauth
        Session::put('last_reauth_at', Carbon::now()->subMinutes(20)->toISOString());
        $this->assertTrue(ReauthenticationController::needsReauth(15));
    }

    /** @test */
    public function it_forces_reauth_by_clearing_timestamp()
    {
        // Set reauth timestamp
        Session::put('last_reauth_at', Carbon::now()->toISOString());
        $this->assertFalse(ReauthenticationController::needsReauth());

        // Force reauth
        ReauthenticationController::forceReauth();
        $this->assertTrue(ReauthenticationController::needsReauth());
    }

    /** @test */
    public function reauth_middleware_redirects_when_reauth_needed()
    {
        $this->actingAs($this->user);

        $request = Request::create('/admin/users/create', 'GET');
        $middleware = new RequireReauthentication();

        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/reauth', $response->headers->get('Location'));
    }

    /** @test */
    public function reauth_middleware_allows_request_when_recently_authenticated()
    {
        $this->actingAs($this->user);
        Session::put('last_reauth_at', Carbon::now()->toISOString());

        $request = Request::create('/admin/users/create', 'GET');
        $middleware = new RequireReauthentication();

        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    /** @test */
    public function reauth_middleware_respects_custom_max_age()
    {
        $this->actingAs($this->user);
        
        // Set reauth timestamp 8 minutes ago
        Session::put('last_reauth_at', Carbon::now()->subMinutes(8)->toISOString());

        $request = Request::create('/admin/users/create', 'GET');
        $middleware = new RequireReauthentication();

        // Should allow with 10 minute max age
        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        }, 10);
        $this->assertEquals(200, $response->getStatusCode());

        // Should redirect with 5 minute max age
        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        }, 5);
        $this->assertEquals(302, $response->getStatusCode());
    }

    /** @test */
    public function session_security_service_validates_session_fingerprint()
    {
        $this->actingAs($this->user);
        
        $request = Request::create('/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.1',
            'HTTP_USER_AGENT' => 'Test Browser'
        ]);

        $sessionService = app(SessionSecurityService::class);
        
        // First validation should pass and store fingerprint
        $result = $sessionService->validateSession($request);
        $this->assertTrue($result['valid']);

        // Same fingerprint should pass
        $result = $sessionService->validateSession($request);
        $this->assertTrue($result['valid']);

        // Different IP should fail with strict validation
        $request2 = Request::create('/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.2',
            'HTTP_USER_AGENT' => 'Test Browser'
        ]);
        
        $result = $sessionService->validateSession($request2, ['strict_ip_validation' => true]);
        $this->assertFalse($result['valid']);
        $this->assertContains('Session IP address mismatch', $result['errors']);
    }

    /** @test */
    public function session_security_service_validates_session_age()
    {
        $this->actingAs($this->user);
        
        $request = Request::create('/test', 'GET');
        $sessionService = app(SessionSecurityService::class);

        // Set old session start time
        Session::put('session_start_time', Carbon::now()->subMinutes(500)->toISOString());

        $result = $sessionService->validateSession($request, ['max_session_age' => 480]);
        $this->assertFalse($result['valid']);
        $this->assertContains('Session has expired due to age', $result['errors']);
    }

    /** @test */
    public function session_security_service_validates_reauth_requirement()
    {
        $this->actingAs($this->user);
        
        $request = Request::create('/test', 'GET');
        $sessionService = app(SessionSecurityService::class);

        // No reauth timestamp should fail
        $result = $sessionService->validateSession($request, ['require_reauth' => true]);
        $this->assertFalse($result['valid']);
        $this->assertContains('Re-authentication required for this operation', $result['errors']);

        // Recent reauth should pass
        Session::put('last_reauth_at', Carbon::now()->toISOString());
        $result = $sessionService->validateSession($request, ['require_reauth' => true]);
        $this->assertTrue($result['valid']);
    }

    /** @test */
    public function session_security_service_regenerates_session()
    {
        $this->actingAs($this->user);
        
        $request = Request::create('/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.1',
            'HTTP_USER_AGENT' => 'Test Browser'
        ]);

        $sessionService = app(SessionSecurityService::class);
        $oldSessionId = Session::getId();

        $sessionService->regenerateSession($request);
        
        $newSessionId = Session::getId();
        $this->assertNotEquals($oldSessionId, $newSessionId);
        
        // Session data should be preserved
        $this->assertEquals('192.168.1.1', Session::get('session_ip'));
        $this->assertEquals('Test Browser', Session::get('session_user_agent'));
    }

    /** @test */
    public function session_security_service_invalidates_session()
    {
        $this->actingAs($this->user);
        
        $request = Request::create('/test', 'GET');
        $sessionService = app(SessionSecurityService::class);

        $this->assertTrue(Auth::check());
        
        $sessionService->invalidateSession($request, 'Test invalidation');
        
        $this->assertFalse(Auth::check());
    }
}