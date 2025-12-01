<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Mockery;

class AuditLoggerTest extends TestCase
{
    protected $auditLogger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditLogger = new AuditLogger();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_audit_logger_can_be_instantiated()
    {
        $this->assertInstanceOf(AuditLogger::class, $this->auditLogger);
    }

    public function test_audit_logger_has_required_methods()
    {
        $this->assertTrue(method_exists($this->auditLogger, 'logSecurityEvent'));
        $this->assertTrue(method_exists($this->auditLogger, 'logSuccessfulAuthentication'));
        $this->assertTrue(method_exists($this->auditLogger, 'logFailedAuthentication'));
        $this->assertTrue(method_exists($this->auditLogger, 'logAccountLockout'));
        $this->assertTrue(method_exists($this->auditLogger, 'logAccountUnlock'));
        $this->assertTrue(method_exists($this->auditLogger, 'logPasswordChange'));
    }
}