<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Role;
use App\Services\SessionSecurityService;
use App\Services\AuditLogger;
use Carbon\Carbon;

class SessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected $sessionSecurityService;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test role
        $role = Role::create([
            'name' => 'Test Role',
            'description' => 'Test role for session security tests'
        ]);

        // Create test user
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'password_changed_at' => Carbon::now(),
        ]);

        $this->sessionSecurityService = app(SessionSecurityService::class);
    }

    /** @test */
    public function it_initializes_session_fingerprint()
    {
        $request = $this->createMockRequest('192.168.1.1', 'Mozilla/5.0');
        
        $this->sessionSecurityService->initializeSessionFingerprint($request);
        
        $this->assertEquals('192.168.1.1', Session::get('session_ip'));
        $this->assertEquals('Mozilla/5.0', Session::get('session_user_agent'));
        $this->assertNotNull(Session::get('session_start_time'));
        $this->assertTrue(Session::get('session_fingerprint_initialized'));
    }

    /** @test */
    public function it_regenerates_session_with_new_fingerprint()
    {
        $request = $this->createMockRequest('192.168.1.1', 'Mozilla/5.0');
        
        Auth::login($this->user);
        $oldSessionId = Session::getId();
        
        $this->sessionSecurityService->regenerateSession($request, false);
        
        $newSessionId = Session::getId();
        $this->assertNotEquals($oldSessionId, $newSessionId);
        $this->assertEquals('192.168.1.1', Session::get('session_ip'));
        $this->assertEquals('Mozilla/5.0', Session::get('session_user_agent'));
    }

    /** @test */
    public function it_regenerates_session_preserving_fingerprint()
    {
        $request = $this->createMockRequest('192.168.1.1', 'Mozilla/5.0');
        
        Auth::login($this->user);
        Session::put('session_ip', '10.0.0.1');
        Session::put('session_user_agent', 'Old Agent');
        
        $oldSessionId = Session::getId();
        
        $this->sessionSecurityService->regenerateSession($request, true);
        
        $newSessionId = Session::getId();
        $this->assertNotEquals($oldSessionId, $newSessionId);
        $this->assertEquals('10.0.0.1', Session::get('session_ip'));
        $this->assertEquals('Old Agent', Session::get('session_user_agent'));
    }

    /** @test */
    public function it_validates_session_successfully()
    {
        $request = $this->createMockRequest('192.168.1.1', 'Mozilla/5.0');
        
        Auth::login($this->user);
        Session::put('session_ip', '192.168.1.1');
        Session::put('session_user_agent', 'Mozilla/5.0');
        Session::put('session_start_time', Carbon::now()->toISOString());
        
        $validation = $this->sessionSecurityService->validateSession($request);
        
        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['errors']);
    }

    /** @test */
    public function it_detects_ip_address_mismatch()
    {
        $request = $this->createMockRequest('192.168.1.2', 'Mozilla/5.0');
        
        Auth::login($this->user);
        Session::put('session_ip', '192.168.1.1');
        Session::put('session_user_agent', 'Mozilla/5.0');
        Session::put('session_start_time', Carbon::now()->toISOString());
        
        $validation = $this->sessionSecurityService->validateSession($request);
        
        $this->assertFalse($validation['valid']);
        $this->assertContains('Incompatibilité d\'adresse IP de session', $validation['errors']);
    }

    /** @test */
    public function it_detects_user_agent_mismatch()
    {
        $request = $this->createMockRequest('192.168.1.1', 'Chrome/90.0');
        
        Auth::login($this->user);
        Session::put('session_ip', '192.168.1.1');
        Session::put('session_user_agent', 'Mozilla/5.0');
        Session::put('session_start_time', Carbon::now()->toISOString());
        
        $validation = $this->sessionSecurityService->validateSession($request);
        
        $this->assertFalse($validation['valid']);
        $this->assertContains('Incompatibilité de User-Agent de session', $validation['errors']);
    }

    /** @test */
    public function it_detects_expired_session()
    {
        $request = $this->createMockRequest('192.168.1.1', 'Mozilla/5.0');
        
        Auth::login($this->user);
        Session::put('session_ip', '192.168.1.1');
        Session::put('session_user_agent', 'Mozilla/5.0');
        Session::put('session_start_time', Carbon::now()->subHours(9)->toISOString());
        
        $validation = $this->sessionSecurityService->validateSession($request);
        
        $this->assertFalse($validation['valid']);
        $this->assertContains('La session a expiré en raison de l\'âge', $validation['errors']);
    }

    /** @test */
    public function it_invalidates_session_for_security_reasons()
    {
        $request = $this->createMockRequest('192.168.1.1', 'Mozilla/5.0');
        
        Auth::login($this->user);
        $this->assertTrue(Auth::check());
        
        $this->sessionSecurityService->invalidateSession($request, 'Test security violation');
        
        $this->assertFalse(Auth::check());
    }

    /**
     * Create a mock request with specific IP and User-Agent
     */
    protected function createMockRequest($ip, $userAgent)
    {
        $request = \Illuminate\Http\Request::create('/', 'GET');
        $request->server->set('REMOTE_ADDR', $ip);
        $request->headers->set('User-Agent', $userAgent);
        
        return $request;
    }
}
