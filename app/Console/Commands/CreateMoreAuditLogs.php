<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AuditLogger;
use App\Models\User;
use Illuminate\Http\Request;

class CreateMoreAuditLogs extends Command
{
    protected $signature = 'audit:create-more';
    protected $description = 'Create more realistic audit log entries';

    public function handle()
    {
        $auditLogger = app(AuditLogger::class);
        $user = User::first();
        
        // Create different request scenarios
        $requests = [
            ['ip' => '192.168.1.100', 'agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'],
            ['ip' => '10.0.0.50', 'agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36'],
            ['ip' => '172.16.0.25', 'agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36'],
        ];
        
        foreach ($requests as $i => $reqData) {
            $request = Request::create('/login', 'POST');
            $request->server->set('REMOTE_ADDR', $reqData['ip']);
            $request->headers->set('User-Agent', $reqData['agent']);
            
            // Create various types of events
            $auditLogger->logFailedAuthentication("attacker{$i}@evil.com", $request);
            $auditLogger->logSuccessfulAuthentication($user->id, $request);
            
            if ($i == 0) {
                $auditLogger->logAccountLockout($user->id, 5, $request);
                $auditLogger->logPasswordPolicyViolation($user->id, ['too_short', 'no_special_chars'], $request);
            }
            
            if ($i == 1) {
                $auditLogger->logRoleChange($user->id, 'User', 'Admin', $user->id, $request);
                $auditLogger->logSecurityConfigChange($user->id, ['max_attempts' => ['old' => 3, 'new' => 5]], $request);
            }
            
            if ($i == 2) {
                $auditLogger->logUnauthorizedAccess('admin_panel', 'view', $user->id, $request);
                $auditLogger->logUserCreation($user->id + 1, $user->id, 'User', $request);
            }
        }
        
        $this->info('Created additional realistic audit log entries!');
        $this->info('Total audit logs now: ' . \App\Models\AuditLog::count());
        
        return 0;
    }
}