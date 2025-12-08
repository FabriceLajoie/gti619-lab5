<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceHttps
{
    /**
     * Traite une requête entrante et force HTTPS. (I messed up, doesn't work :((( )
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if ($this->shouldEnforceHttps() && !$this->isSecure($request)) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }

    /**
     * Détermine si requête sécurisée.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function isSecure(Request $request): bool
    {
        return $request->secure() || $request->getScheme() === 'https';
    }

    /**
     *  HTTPS forcé?
     *
     * @return bool
     */
    protected function shouldEnforceHttps(): bool
    {
        return app()->environment('production');
    }
}
