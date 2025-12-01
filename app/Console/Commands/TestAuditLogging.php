<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AuditLogger;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class TestAuditLogging extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the audit logging system';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Testing Audit Logging System...');
        
        $auditLogger = app(AuditLogger::class);
        
        // Create a test user if none exists
        $user = User::first();
        if (!$user) {
            $this->error('No users found in database. Please create a user first.');
            return 1;
        }
        
        $this->info("Using user: {$user->name} (ID: {$user->id})");
        
        // Create a mock request
        $request = Request::create('/test', 'POST');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $request->headers->set('User-Agent', 'Test Console Command');
        
        // Test different types of audit logs
        $this->info('Creating test audit logs...');
        
        // 1. Test successful authentication
        $log1 = $auditLogger->logSuccessfulAuthentication($user->id, $request);
        $this->info("✓ Created successful authentication log (ID: {$log1->id})");
        
        // 2. Test failed authentication
        $log2 = $auditLogger->logFailedAuthentication('test@ets.com', $request);
        $this->info("✓ Created failed authentication log (ID: {$log2->id})");
        
        // 3. Test password change
        $log3 = $auditLogger->logPasswordChange($user->id, false, $request);
        $this->info("✓ Created password change log (ID: {$log3->id})");
        
        // 4. Test security event
        $log4 = $auditLogger->logSecurityEvent('test_security_event', $user->id, [
            'message' => 'This is a test security event',
            'test_data' => 'sample_value'
        ], $request);
        $this->info("✓ Created security event log (ID: {$log4->id})");
        
        // 5. Test unauthorized access
        $log5 = $auditLogger->logUnauthorizedAccess('admin_panel', 'view', $user->id, $request);
        $this->info("✓ Created unauthorized access log (ID: {$log5->id})");
        
        // Display summary
        $this->info('');
        $this->info('Test Summary:');
        $this->table(
            ['ID', 'Event Type', 'User ID', 'IP Address', 'Severity', 'Created At'],
            [
                [$log1->id, $log1->event_type, $log1->user_id, $log1->ip_address, $log1->severity, $log1->created_at],
                [$log2->id, $log2->event_type, $log2->user_id ?? 'N/A', $log2->ip_address, $log2->severity, $log2->created_at],
                [$log3->id, $log3->event_type, $log3->user_id, $log3->ip_address, $log3->severity, $log3->created_at],
                [$log4->id, $log4->event_type, $log4->user_id, $log4->ip_address, $log4->severity, $log4->created_at],
                [$log5->id, $log5->event_type, $log5->user_id, $log5->ip_address, $log5->severity, $log5->created_at],
            ]
        );
        
        // Test querying capabilities
        $this->info('');
        $this->info('Testing query capabilities...');
        
        $totalLogs = AuditLog::count();
        $this->info("Total audit logs in database: {$totalLogs}");
        
        $authLogs = AuditLog::authenticationEvents()->count();
        $this->info("Authentication events: {$authLogs}");
        
        $securityLogs = AuditLog::securityEvents()->count();
        $this->info("Security events: {$securityLogs}");
        
        $highSeverityLogs = AuditLog::whereIn('event_type', [
            'account_locked',
            'unauthorized_access',
            'password_policy_violation',
            'session_hijack_detected',
        ])->count();
        $this->info("High severity events: {$highSeverityLogs}");
        
        $userLogs = AuditLog::byUser($user->id)->count();
        $this->info("Logs for user {$user->name}: {$userLogs}");
        
        $this->info('');
        $this->info('✅ Audit logging test completed successfully!');
        $this->info('You can now view these logs in the admin panel at /admin/audit-logs');
        
        return 0;
    }
}