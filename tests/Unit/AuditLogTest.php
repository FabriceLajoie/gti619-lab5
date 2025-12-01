<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_belongs_to_a_user()
    {
        $auditLog = AuditLog::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(User::class, $auditLog->user);
        $this->assertEquals($this->user->id, $auditLog->user->id);
    }

    /** @test */
    public function it_can_have_null_user()
    {
        $auditLog = AuditLog::factory()->create([
            'user_id' => null,
        ]);

        $this->assertNull($auditLog->user);
    }

    /** @test */
    public function it_casts_details_to_array()
    {
        $details = ['message' => 'Test message', 'data' => ['key' => 'value']];
        
        $auditLog = AuditLog::factory()->create([
            'details' => $details,
        ]);

        $this->assertIsArray($auditLog->details);
        $this->assertEquals($details, $auditLog->details);
    }

    /** @test */
    public function it_can_scope_by_event_type()
    {
        AuditLog::factory()->create(['event_type' => 'login_success']);
        AuditLog::factory()->create(['event_type' => 'login_failed']);
        AuditLog::factory()->create(['event_type' => 'login_success']);

        $successLogs = AuditLog::byEventType('login_success')->get();
        $failedLogs = AuditLog::byEventType('login_failed')->get();

        $this->assertCount(2, $successLogs);
        $this->assertCount(1, $failedLogs);
    }

    /** @test */
    public function it_can_scope_by_user()
    {
        $otherUser = User::factory()->create();

        AuditLog::factory()->create(['user_id' => $this->user->id]);
        AuditLog::factory()->create(['user_id' => $this->user->id]);
        AuditLog::factory()->create(['user_id' => $otherUser->id]);

        $userLogs = AuditLog::byUser($this->user->id)->get();

        $this->assertCount(2, $userLogs);
        $userLogs->each(function ($log) {
            $this->assertEquals($this->user->id, $log->user_id);
        });
    }

    /** @test */
    public function it_can_scope_by_date_range()
    {
        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now()->subDays(1);

        AuditLog::factory()->create(['created_at' => Carbon::now()->subDays(10)]);
        AuditLog::factory()->create(['created_at' => Carbon::now()->subDays(5)]);
        AuditLog::factory()->create(['created_at' => Carbon::now()->subDays(3)]);
        AuditLog::factory()->create(['created_at' => Carbon::now()]);

        $logsInRange = AuditLog::byDateRange($startDate, $endDate)->get();

        $this->assertCount(2, $logsInRange);
    }

    /** @test */
    public function it_can_scope_recent_logs()
    {
        AuditLog::factory()->create(['created_at' => Carbon::now()->subDays(10)]);
        AuditLog::factory()->create(['created_at' => Carbon::now()->subDays(5)]);
        AuditLog::factory()->create(['created_at' => Carbon::now()->subDays(2)]);
        AuditLog::factory()->create(['created_at' => Carbon::now()]);

        $recentLogs = AuditLog::recent(7)->get();

        $this->assertCount(3, $recentLogs);
    }

    /** @test */
    public function it_can_scope_authentication_events()
    {
        AuditLog::factory()->create(['event_type' => 'login_success']);
        AuditLog::factory()->create(['event_type' => 'login_failed']);
        AuditLog::factory()->create(['event_type' => 'account_locked']);
        AuditLog::factory()->create(['event_type' => 'password_changed']);
        AuditLog::factory()->create(['event_type' => 'user_logout']);

        $authEvents = AuditLog::authenticationEvents()->get();

        $this->assertCount(4, $authEvents);
        $authEvents->each(function ($log) {
            $this->assertContains($log->event_type, [
                'login_success',
                'login_failed',
                'account_locked',
                'account_unlocked',
                'user_logout'
            ]);
        });
    }

    /** @test */
    public function it_can_scope_security_events()
    {
        AuditLog::factory()->create(['event_type' => 'password_changed']);
        AuditLog::factory()->create(['event_type' => 'role_changed']);
        AuditLog::factory()->create(['event_type' => 'unauthorized_access']);
        AuditLog::factory()->create(['event_type' => 'login_success']);
        AuditLog::factory()->create(['event_type' => 'user_created']);

        $securityEvents = AuditLog::securityEvents()->get();

        $this->assertCount(4, $securityEvents);
        $securityEvents->each(function ($log) {
            $this->assertContains($log->event_type, [
                'password_changed',
                'password_policy_violation',
                'role_changed',
                'security_config_changed',
                'unauthorized_access',
                'user_created'
            ]);
        });
    }

    /** @test */
    public function it_formats_event_type_for_display()
    {
        $testCases = [
            'login_success' => 'Successful Login',
            'login_failed' => 'Failed Login',
            'account_locked' => 'Account Locked',
            'password_changed' => 'Password Changed',
            'custom_event' => 'Custom Event',
        ];

        foreach ($testCases as $eventType => $expectedFormat) {
            $auditLog = AuditLog::factory()->create(['event_type' => $eventType]);
            $this->assertEquals($expectedFormat, $auditLog->formatted_event_type);
        }
    }

    /** @test */
    public function it_determines_severity_level()
    {
        $highSeverityEvents = [
            'account_locked',
            'unauthorized_access',
            'password_policy_violation',
            'session_hijack_detected',
        ];

        $mediumSeverityEvents = [
            'login_failed',
            'password_changed',
            'role_changed',
            'security_config_changed',
            'user_created',
        ];

        $lowSeverityEvents = [
            'login_success',
            'user_logout',
        ];

        foreach ($highSeverityEvents as $eventType) {
            $auditLog = AuditLog::factory()->create(['event_type' => $eventType]);
            $this->assertEquals('high', $auditLog->severity);
        }

        foreach ($mediumSeverityEvents as $eventType) {
            $auditLog = AuditLog::factory()->create(['event_type' => $eventType]);
            $this->assertEquals('medium', $auditLog->severity);
        }

        foreach ($lowSeverityEvents as $eventType) {
            $auditLog = AuditLog::factory()->create(['event_type' => $eventType]);
            $this->assertEquals('low', $auditLog->severity);
        }
    }

    /** @test */
    public function it_provides_severity_css_classes()
    {
        $auditLogHigh = AuditLog::factory()->create(['event_type' => 'account_locked']);
        $auditLogMedium = AuditLog::factory()->create(['event_type' => 'password_changed']);
        $auditLogLow = AuditLog::factory()->create(['event_type' => 'login_success']);

        $this->assertEquals('text-red-600 bg-red-100', $auditLogHigh->severity_css_class);
        $this->assertEquals('text-yellow-600 bg-yellow-100', $auditLogMedium->severity_css_class);
        $this->assertEquals('text-green-600 bg-green-100', $auditLogLow->severity_css_class);
    }

    /** @test */
    public function it_handles_empty_details()
    {
        $auditLog = AuditLog::factory()->create(['details' => null]);

        $this->assertNull($auditLog->details);
    }

    /** @test */
    public function it_can_be_created_with_all_attributes()
    {
        $attributes = [
            'event_type' => 'test_event',
            'user_id' => $this->user->id,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Test User Agent',
            'details' => ['message' => 'Test message'],
        ];

        $auditLog = AuditLog::create($attributes);

        $this->assertEquals($attributes['event_type'], $auditLog->event_type);
        $this->assertEquals($attributes['user_id'], $auditLog->user_id);
        $this->assertEquals($attributes['ip_address'], $auditLog->ip_address);
        $this->assertEquals($attributes['user_agent'], $auditLog->user_agent);
        $this->assertEquals($attributes['details'], $auditLog->details);
    }
}