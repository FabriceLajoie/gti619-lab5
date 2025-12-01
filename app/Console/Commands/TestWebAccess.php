<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestWebAccess extends Command
{
    protected $signature = 'test:web';
    protected $description = 'Test web access to the application';

    public function handle()
    {
        $this->info('Testing web application access...');
        
        try {
            // Test if we can make an internal request to the login page
            $response = Http::get('http://localhost/login');
            
            if ($response->successful()) {
                $this->info('✅ Login page is accessible!');
                $this->info('Status: ' . $response->status());
                $this->info('You can now access the application at: http://localhost');
                $this->info('Login credentials:');
                $this->info('  Admin: admin@ets.com / Admin123!');
                $this->info('  Residential: residential@ets.com / User123!');
                $this->info('  Business: business@ets.com / Business123!');
            } else {
                $this->error('❌ Login page returned status: ' . $response->status());
            }
        } catch (\Exception $e) {
            $this->error('❌ Error accessing login page: ' . $e->getMessage());
            $this->info('This might be normal if testing from inside the container.');
            $this->info('Try accessing http://localhost directly in your browser.');
        }
        
        // Test database connectivity
        try {
            $userCount = \App\Models\User::count();
            $auditCount = \App\Models\AuditLog::count();
            
            $this->info('');
            $this->info('Database Status:');
            $this->info("✅ Users in database: {$userCount}");
            $this->info("✅ Audit logs in database: {$auditCount}");
            
        } catch (\Exception $e) {
            $this->error('❌ Database error: ' . $e->getMessage());
        }
        
        return 0;
    }
}