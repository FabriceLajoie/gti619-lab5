<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SessionSecurityService;

class CleanupExpiredSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sessions:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired sessions from the database';

    protected $sessionSecurityService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(SessionSecurityService $sessionSecurityService)
    {
        parent::__construct();
        $this->sessionSecurityService = $sessionSecurityService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Cleaning up expired sessions...');
        
        $deleted = $this->sessionSecurityService->cleanupExpiredSessions();
        
        $this->info("Successfully cleaned up {$deleted} expired session(s).");
        
        return 0;
    }
}
