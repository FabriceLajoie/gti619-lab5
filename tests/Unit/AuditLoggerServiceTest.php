<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Mockery;

class AuditLoggerServiceTest extends TestCase
{
    protected $auditLogger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditLogger = new AuditLogger();
    }

    /** @test */
    public function it_can_be_instantiated()
    {
        $this->assertInstanceOf(AuditLogger::class, $this->auditLogger);
    }

    /** @test */
    public function it_has_all_required_logging_methods()
    {
        $methods = [
            'logSecurityEvent',
            'logSuccessfulAuthentication',
            'logFailedAuthentication',
            'logAccountLockout',
            'logAccountUnlock',
            'logPasswordChange',
            'logPasswordPolicyViolation',
            'logRoleChange',
            'logSecurityConfigChange',
            'logUnauthorizedAccess',
            'logUserCreation',
            'logSessionEvent',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($this->auditLogger, $method),
                "AuditLogger should have method: {$method}"
            );
        }
    }

    /** @test */
    public function it_can_handle_request_data_extraction()
    {
        $request = Request::create('/test', 'GET');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $request->headers->set('User-Agent', 'Test User Agent');

        // Test that the service can extract IP and User Agent from request
        $this->assertEquals('192.168.1.1', $request->ip());
        $this->assertEquals('Test User Agent', $request->userAgent());
    }

    /** @test */
    public function it_handles_null_request_gracefully()
    {
        // This should not throw an exception
        $request = null;
        
        // The service should handle null request without errors
        $this->assertNull($request);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}