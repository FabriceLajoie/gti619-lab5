<?php

namespace App\Providers;

use App\Services\PBKDF2PasswordHasher;
use App\Services\PasswordHistoryService;
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
        // Register PBKDF2PasswordHasher as singleton
        $this->app->singleton(PBKDF2PasswordHasher::class, function ($app) {
            // Get iterations from config or use default
            $iterations = config('security.pbkdf2_iterations', 100000);
            return new PBKDF2PasswordHasher($iterations);
        });
        
        // Register PasswordHistoryService as singleton
        $this->app->singleton(PasswordHistoryService::class, function ($app) {
            $hasher = $app->make(PBKDF2PasswordHasher::class);
            return new PasswordHistoryService($hasher);
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
