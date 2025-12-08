<?php

namespace App\Providers;

use App\Services\PBKDF2PasswordHasher;
use App\Services\PasswordHistoryService;
use App\Services\SessionSecurityService;
use Illuminate\Support\ServiceProvider;

class SecurityServiceProvider extends ServiceProvider
{
    /**
     * Register services
     *
     * @return void
     */
    public function register()
    {
        // singleton
        $this->app->singleton(PBKDF2PasswordHasher::class, function ($app) {
            $iterations = config('security.pbkdf2_iterations', 100000);
            return new PBKDF2PasswordHasher($iterations);
        });
        
        // singleton
        $this->app->singleton(PasswordHistoryService::class, function ($app) {
            $hasher = $app->make(PBKDF2PasswordHasher::class);
            return new PasswordHistoryService($hasher);
        });
        
        // singleton
        $this->app->singleton(SessionSecurityService::class, function ($app) {
            $auditLogger = $app->make(\App\Services\AuditLogger::class);
            return new SessionSecurityService($auditLogger);
        });
    }

    /**
     * Bootstrap services
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
