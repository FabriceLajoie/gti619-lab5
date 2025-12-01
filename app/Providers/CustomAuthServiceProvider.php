<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use App\Auth\PBKDF2UserProvider;
use App\Services\PBKDF2PasswordHasher;

class CustomAuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        Auth::provider('pbkdf2', function ($app, array $config) {
            return new PBKDF2UserProvider(
                $app['hash'],
                $config['model'],
                $app->make(PBKDF2PasswordHasher::class)
            );
        });
    }
}