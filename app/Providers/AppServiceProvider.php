<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register services
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     *
     * @return void
     */
    public function boot()
    {
        if( app()->environment('production') ) {
            \URL::forceScheme('https');
        }
    }
}
