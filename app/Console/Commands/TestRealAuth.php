<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\AuthController;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TestRealAuth extends Command
{
    protected $signature = 'audit:test-auth';
    protected $description = 'Test audit logging with real authentication scenarios';

    public function handle()
    {
        $this->info('Testing audit logging with authentication scenarios...');
        
        $user = User::where('email', 'admin@ets.com')->first();
        if (!$user) {
            $this->error('Admin user not found');
            return 1;
        }
        
        $initialLogCount = AuditLog::count();
        $this->info("Initial audit log count: {$initialLogCount}");
        
        // Test 1: Successful login simulation
        $this->info('Testing successful login audit logging...');
        $request = Request::create('/login', 'POST', [
            'email' => 'admin@ets.com',
            'password' => 'Admin123!'
        ]);
        $request->server->set('REMOTE_ADDR', '192.168.1.200');
        $request->headers->set('User-Agent', 'Test Browser for Audit');
        
        // Simulate the audit logging that happens in AuthController
        $auditLogger = app(\App\Services\AuditLogger::class);
        $auditLogger->logSuccessfulAuthentication($user->id, $request);
        
        // Test 2: Failed login simulation
        $this->info('Testing failed login audit logging...');
        $failedRequest = Request::create('/login', 'POST', [
            'email' => 'admin@ets.com',
            'password' => 'wrongpassword'
        ]);
        $failedRequest->server->set('REMOTE_ADDR', '192.168.1.201');
        $failedRequest->headers->set('User-Agent', 'Potential Attacker Browser');
        
        $auditLogger->logFailedAuthentication('admin@ets.com', $failedRequest);
        
        // Test 3: Account lockout simulation
        $this->info('Testing account lockout audit logging...');
        $auditLogger->logAccountLockout($user->id, 5, $failedRequest);
        
        // Test 4: Password change simulation
        $this->info('Testing password change audit logging...');
        $pwdRequest = Request::create('/password/change', 'POST');
        $pwdRequest->server->set('REMOTE_ADDR', '192.168.1.200');
        $pwdRequest->headers->set('User-Agent', 'Test Browser for Audit');
        
        $auditLogger->logPasswordChange($user->id, false, $pwdRequest);
        
        $finalLogCount = AuditLog::count();
        $newLogs = $finalLogCount - $initialLogCount;
        
        $this->info("Final audit log count: {$finalLogCount}");
        $this->info("New logs created: {$newLogs}");
        
        // Show recent logs
        $this->info('Recent audit logs:');
        $recentLogs = AuditLog::latest()->take(5)->get();
        
        $this->table(
            ['ID', 'Event Type', 'User', 'IP Address', 'Created At'],
            $recentLogs->map(function ($log) {
                return [
                    $log->id,
                    $log->formatted_event_type,
                    $log->user ? $log->user->name : 'N/A',
                    $log->ip_address,
                    $log->created_at->format('Y-m-d H:i:s')
                ];
            })->toArray()
        );
        
        $this->info('✅ Authentication audit logging test completed!');
        $this->info('You can view these logs at: http://localhost/admin/audit-logs');
        $this->info('Login with: admin@ets.com / Admin123!');
        
        return 0;
    }
}