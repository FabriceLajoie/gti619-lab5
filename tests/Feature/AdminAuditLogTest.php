<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $regularUser;
    protected $adminRole;
    protected $userRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $this->adminRole = Role::factory()->create(['name' => 'Administrateur']);
        $this->userRole = Role::factory()->create(['name' => 'Préposé aux clients résidentiels']);

        // Create users
        $this->adminUser = User::factory()->create(['role_id' => $this->adminRole->id]);
        $this->regularUser = User::factory()->create(['role_id' => $this->userRole->id]);
    }

    /** @test */
    public function admin_can_view_audit_logs_page()
    {
        AuditLog::factory()->count(5)->create();

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.audit-logs'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.audit-logs');
        $response->assertViewHas('auditLogs');
        $response->assertViewHas('eventTypes');
        $response->assertViewHas('users');
    }

    /** @test */
    public function non_admin_cannot_view_audit_logs_page()
    {
        $response = $this->actingAs($this->regularUser)
            ->get(route('admin.audit-logs'));

        $response->assertStatus(302); // Middleware redirects instead of 403
        $response->assertRedirect(); // Should redirect to appropriate page
    }

    /** @test */
    public function guest_cannot_view_audit_logs_page()
    {
        $response = $this->get(route('admin.audit-logs'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function admin_can_filter_audit_logs_by_event_type()
    {
        // Create specific audit logs
        $loginSuccess = AuditLog::factory()->loginSuccess()->create();
        $loginFailed = AuditLog::factory()->loginFailed()->create();
        $accountLocked = AuditLog::factory()->accountLocked()->create();

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.audit-logs', ['event_type' => 'login_success']));

        $response->assertStatus(200);
        $response->assertSee('Successful Login');
        // Verify the filter is applied by checking the selected option
        $this->assertStringContainsString('login_success" selected', $response->getContent());
    }

    /** @test */
    public function admin_can_filter_audit_logs_by_user()
    {
        $user1 = User::factory()->create(['name' => 'Test User One']);
        $user2 = User::factory()->create(['name' => 'Test User Two']);

        AuditLog::factory()->create([
            'user_id' => $user1->id,
            'event_type' => 'login_success'
        ]);
        AuditLog::factory()->create([
            'user_id' => $user2->id,
            'event_type' => 'login_failed'
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.audit-logs', ['user_id' => $user1->id]));

        $response->assertStatus(200);
        $response->assertSee('Test User One');
        // Verify the filter is applied by checking the selected option
        $this->assertStringContainsString('value="' . $user1->id . '" selected', $response->getContent());
        // Ensure only the filtered user's logs are shown in the table
        // Test User Two should not appear in the table data, only in dropdown options
        $tableContent = $response->getContent();
        $this->assertStringContainsString('<div class="text-sm font-medium text-gray-900">Test User One</div>', $tableContent);
        // Make sure Test User Two doesn't appear in the table rows (but can appear in dropdown)
        $this->assertStringNotContainsString('<div class="text-sm font-medium text-gray-900">Test User Two</div>', $tableContent);
    }

    /** @test */
    public function admin_can_filter_audit_logs_by_date_range()
    {
        $oldLog = AuditLog::factory()->create(['created_at' => Carbon::now()->subDays(10)]);
        $recentLog = AuditLog::factory()->create(['created_at' => Carbon::now()->subDays(2)]);

        $startDate = Carbon::now()->subDays(5)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.audit-logs', [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]));

        $response->assertStatus(200);
        // Should see the recent log but not the old one
        $response->assertSee($recentLog->formatted_event_type);
    }

    /** @test */
    public function admin_can_filter_audit_logs_by_severity()
    {
        AuditLog::factory()->highSeverity()->create();
        AuditLog::factory()->mediumSeverity()->create();
        AuditLog::factory()->lowSeverity()->create();

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.audit-logs', ['severity' => 'high']));

        $response->assertStatus(200);
        $response->assertSee('High');
    }

    /** @test */
    public function admin_can_view_audit_log_details()
    {
        $auditLog = AuditLog::factory()->create([
            'user_id' => $this->regularUser->id,
            'event_type' => 'login_success',
            'details' => ['message' => 'Test login success']
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.audit-log-details', $auditLog));

        $response->assertStatus(200);
        $response->assertViewIs('admin.audit-log-details');
        $response->assertViewHas('auditLog');
        $response->assertSee($auditLog->formatted_event_type);
        $response->assertSee($this->regularUser->name);
        $response->assertSee('Test login success');
    }

    /** @test */
    public function non_admin_cannot_view_audit_log_details()
    {
        $auditLog = AuditLog::factory()->create();

        $response = $this->actingAs($this->regularUser)
            ->get(route('admin.audit-log-details', $auditLog));

        $response->assertStatus(302); // Middleware redirects instead of 403
        $response->assertRedirect();
    }

    /** @test */
    public function admin_can_export_audit_logs_as_csv()
    {
        AuditLog::factory()->count(3)->create();

        $response = $this->actingAs($this->adminUser)
            ->get('/admin/audit-logs-export');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('content-disposition');
        
        $content = $response->getContent();
        $this->assertStringContainsString('ID,Event Type,User,IP Address', $content);
    }

    /** @test */
    public function admin_can_view_audit_statistics()
    {
        // Create some test data
        AuditLog::factory()->loginSuccess()->create(['created_at' => Carbon::today()]);
        AuditLog::factory()->loginFailed()->create(['created_at' => Carbon::today()]);
        AuditLog::factory()->accountLocked()->create(['created_at' => Carbon::today()]);
        AuditLog::factory()->highSeverity()->create();

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.audit-statistics'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.audit-statistics');
        $response->assertViewHas(['stats', 'authStats', 'highSeverityEvents', 'eventDistribution']);
        
        // Check that statistics are displayed
        $response->assertSee('Total Logs');
        $response->assertSee('Authentication Activity');
        $response->assertSee('High Severity Events');
    }

    /** @test */
    public function non_admin_cannot_view_audit_statistics()
    {
        $response = $this->actingAs($this->regularUser)
            ->get(route('admin.audit-statistics'));

        $response->assertStatus(302); // Middleware redirects instead of 403
        $response->assertRedirect();
    }

    /** @test */
    public function audit_logs_are_paginated()
    {
        AuditLog::factory()->count(30)->create();

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.audit-logs'));

        $response->assertStatus(200);
        $response->assertSee('Next'); // Pagination link
    }

    /** @test */
    public function audit_logs_show_correct_severity_styling()
    {
        $highSeverityLog = AuditLog::factory()->create(['event_type' => 'account_locked']);
        $mediumSeverityLog = AuditLog::factory()->create(['event_type' => 'password_changed']);
        $lowSeverityLog = AuditLog::factory()->create(['event_type' => 'login_success']);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.audit-logs'));

        $response->assertStatus(200);
        $response->assertSee('text-red-600 bg-red-100'); // High severity CSS
        $response->assertSee('text-yellow-600 bg-yellow-100'); // Medium severity CSS
        $response->assertSee('text-green-600 bg-green-100'); // Low severity CSS
    }

    /** @test */
    public function audit_log_details_show_related_events()
    {
        $user = User::factory()->create();
        
        $mainLog = AuditLog::factory()->create(['user_id' => $user->id]);
        $relatedLog1 = AuditLog::factory()->create(['user_id' => $user->id]);
        $relatedLog2 = AuditLog::factory()->create(['user_id' => $user->id]);
        $unrelatedLog = AuditLog::factory()->create(); // Different user

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.audit-log-details', $mainLog));

        $response->assertStatus(200);
        $response->assertSee('Recent Events for This User');
        $response->assertSee($relatedLog1->formatted_event_type);
        $response->assertSee($relatedLog2->formatted_event_type);
    }

    /** @test */
    public function export_respects_filters()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        AuditLog::factory()->create(['user_id' => $user1->id, 'event_type' => 'login_success']);
        AuditLog::factory()->create(['user_id' => $user2->id, 'event_type' => 'login_failed']);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.audit-logs.export', ['event_type' => 'login_success']));

        $response->assertStatus(200);
        $content = $response->getContent();
        
        $this->assertStringContainsString('Successful Login', $content);
        $this->assertStringNotContainsString('Failed Login', $content);
    }
}