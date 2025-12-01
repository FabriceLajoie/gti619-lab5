<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\AuditLogger;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    protected $auditLogger;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditLogger = new AuditLogger();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_can_log_a_general_security_event()
    {
        $eventType = 'test_event';
        $userId = $this->user->id;
        $details = ['message' => 'Test security event'];

        $request = Request::create('/test', 'GET');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $request->server->set('HTTP_USER_AGENT', 'Test User Agent');

        $auditLog = $this->auditLogger->logSecurityEvent($eventType, $userId, $details, $request);

        $this->assertInstanceOf(AuditLog::class, $auditLog);
        $this->assertEquals($eventType, $auditLog->event_type);
        $this->assertEquals($userId, $auditLog->user_id);
        $this->assertEquals('192.168.1.1', $auditLog->ip_address);
        $this->assertEquals('Test User Agent', $auditLog->user_agent);
        $this->assertEquals($details, $auditLog->details);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => $eventType,
            'user_id' => $userId,
            'ip_address' => '192.168.1.1',
        ]);
    }

    /** @test */
    public function it_can_log_successful_authentication()
    {
        $request = Request::create('/login', 'POST');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');

        $auditLog = $this->auditLogger->logSuccessfulAuthentication($this->user->id, $request);

        $this->assertEquals('login_success', $auditLog->event_type);
        $this->assertEquals($this->user->id, $auditLog->user_id);
        $this->assertEquals('User successfully authenticated', $auditLog->details['message']);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'login_success',
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_can_log_failed_authentication()
    {
        $email = 'test@ets.com';
        $request = Request::create('/login', 'POST');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');

        $auditLog = $this->auditLogger->logFailedAuthentication($email, $request);

        $this->assertEquals('login_failed', $auditLog->event_type);
        $this->assertNull($auditLog->user_id);
        $this->assertEquals($email, $auditLog->details['email']);
        $this->assertEquals('Failed authentication attempt', $auditLog->details['message']);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'login_failed',
            'user_id' => null,
        ]);
    }

    /** @test */
    public function it_can_log_account_lockout()
    {
        $failedAttempts = 5;
        $request = Request::create('/login', 'POST');

        $auditLog = $this->auditLogger->logAccountLockout($this->user->id, $failedAttempts, $request);

        $this->assertEquals('account_locked', $auditLog->event_type);
        $this->assertEquals($this->user->id, $auditLog->user_id);
        $this->assertEquals($failedAttempts, $auditLog->details['failed_attempts']);
        $this->assertEquals('Account locked due to excessive failed login attempts', $auditLog->details['message']);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'account_locked',
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_can_log_account_unlock()
    {
        $adminUser = User::factory()->create();
        $request = Request::create('/admin/unlock', 'POST');

        $auditLog = $this->auditLogger->logAccountUnlock($this->user->id, $adminUser->id, $request);

        $this->assertEquals('account_unlocked', $auditLog->event_type);
        $this->assertEquals($this->user->id, $auditLog->user_id);
        $this->assertEquals($adminUser->id, $auditLog->details['unlocked_by_user_id']);
        $this->assertEquals('Account unlocked by administrator', $auditLog->details['message']);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'account_unlocked',
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_can_log_password_change()
    {
        $request = Request::create('/password/change', 'POST');

        // Test voluntary password change
        $auditLog = $this->auditLogger->logPasswordChange($this->user->id, false, $request);

        $this->assertEquals('password_changed', $auditLog->event_type);
        $this->assertEquals($this->user->id, $auditLog->user_id);
        $this->assertFalse($auditLog->details['forced']);
        $this->assertEquals('Password changed by user', $auditLog->details['message']);

        // Test forced password change
        $auditLog = $this->auditLogger->logPasswordChange($this->user->id, true, $request);

        $this->assertTrue($auditLog->details['forced']);
        $this->assertEquals('Password changed (forced)', $auditLog->details['message']);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'password_changed',
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_can_log_password_policy_violation()
    {
        $violations = ['too_short', 'no_uppercase'];
        $request = Request::create('/password/change', 'POST');

        $auditLog = $this->auditLogger->logPasswordPolicyViolation($this->user->id, $violations, $request);

        $this->assertEquals('password_policy_violation', $auditLog->event_type);
        $this->assertEquals($this->user->id, $auditLog->user_id);
        $this->assertEquals($violations, $auditLog->details['violations']);
        $this->assertEquals('Password policy violation detected', $auditLog->details['message']);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'password_policy_violation',
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_can_log_role_change()
    {
        $adminUser = User::factory()->create();
        $oldRole = 'user';
        $newRole = 'admin';
        $request = Request::create('/admin/users', 'PUT');

        $auditLog = $this->auditLogger->logRoleChange($this->user->id, $oldRole, $newRole, $adminUser->id, $request);

        $this->assertEquals('role_changed', $auditLog->event_type);
        $this->assertEquals($this->user->id, $auditLog->user_id);
        $this->assertEquals($oldRole, $auditLog->details['old_role']);
        $this->assertEquals($newRole, $auditLog->details['new_role']);
        $this->assertEquals($adminUser->id, $auditLog->details['changed_by_user_id']);
        $this->assertEquals("Role changed from {$oldRole} to {$newRole}", $auditLog->details['message']);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'role_changed',
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_can_log_security_config_change()
    {
        $changes = ['max_login_attempts' => ['old' => 3, 'new' => 5]];
        $request = Request::create('/admin/security', 'PUT');

        $auditLog = $this->auditLogger->logSecurityConfigChange($this->user->id, $changes, $request);

        $this->assertEquals('security_config_changed', $auditLog->event_type);
        $this->assertEquals($this->user->id, $auditLog->user_id);
        $this->assertEquals($changes, $auditLog->details['changes']);
        $this->assertEquals('Security configuration updated', $auditLog->details['message']);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'security_config_changed',
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_can_log_unauthorized_access()
    {
        $resource = 'admin_panel';
        $action = 'view';
        $request = Request::create('/admin', 'GET');

        $auditLog = $this->auditLogger->logUnauthorizedAccess($resource, $action, $this->user->id, $request);

        $this->assertEquals('unauthorized_access', $auditLog->event_type);
        $this->assertEquals($this->user->id, $auditLog->user_id);
        $this->assertEquals($resource, $auditLog->details['resource']);
        $this->assertEquals($action, $auditLog->details['action']);
        $this->assertEquals("Unauthorized access attempt to {$resource}:{$action}", $auditLog->details['message']);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'unauthorized_access',
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_can_log_user_creation()
    {
        $adminUser = User::factory()->create();
        $newUser = User::factory()->create();
        $role = 'user';
        $request = Request::create('/admin/users', 'POST');

        $auditLog = $this->auditLogger->logUserCreation($newUser->id, $adminUser->id, $role, $request);

        $this->assertEquals('user_created', $auditLog->event_type);
        $this->assertEquals($newUser->id, $auditLog->user_id);
        $this->assertEquals($adminUser->id, $auditLog->details['created_by_user_id']);
        $this->assertEquals($role, $auditLog->details['role']);
        $this->assertEquals("New user created with role: {$role}", $auditLog->details['message']);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'user_created',
            'user_id' => $newUser->id,
        ]);
    }

    /** @test */
    public function it_can_log_session_events()
    {
        $sessionEvent = 'created';
        $details = ['session_id' => 'test_session_123'];
        $request = Request::create('/login', 'POST');

        $auditLog = $this->auditLogger->logSessionEvent($sessionEvent, $this->user->id, $details, $request);

        $this->assertEquals('session_created', $auditLog->event_type);
        $this->assertEquals($this->user->id, $auditLog->user_id);
        $this->assertEquals('test_session_123', $auditLog->details['session_id']);
        $this->assertEquals("Session event: {$sessionEvent}", $auditLog->details['message']);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'session_created',
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_uses_authenticated_user_when_no_user_id_provided()
    {
        Auth::login($this->user);

        $auditLog = $this->auditLogger->logSecurityEvent('test_event');

        $this->assertEquals($this->user->id, $auditLog->user_id);
    }

    /** @test */
    public function it_handles_null_request_gracefully()
    {
        $auditLog = $this->auditLogger->logSecurityEvent('test_event', $this->user->id, [], null);

        $this->assertEquals('test_event', $auditLog->event_type);
        $this->assertEquals($this->user->id, $auditLog->user_id);
        $this->assertNull($auditLog->ip_address);
        $this->assertNull($auditLog->user_agent);
    }

    /** @test */
    public function it_stores_details_as_json()
    {
        $details = [
            'complex_data' => [
                'nested' => 'value',
                'array' => [1, 2, 3]
            ]
        ];

        $auditLog = $this->auditLogger->logSecurityEvent('test_event', $this->user->id, $details);

        $this->assertEquals($details, $auditLog->details);
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'test_event',
            'details' => json_encode($details),
        ]);
    }
}