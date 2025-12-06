<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceHttps
{
    /**
     * Traite une requête entrante et force HTTPS.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Ne forcer HTTPS qu'en production ou quand explicitement activé
        // En développement local, on permet HTTP pour faciliter le développement
        if ($this->shouldEnforceHttps() && !$this->isSecure($request)) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }

    /**
     * Détermine si la requête est sécurisée.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function isSecure(Request $request): bool
    {
        return $request->secure() || $request->getScheme() === 'https';
    }

    /**
     * Détermine si HTTPS doit être forcé.
     *
     * @return bool
     */
    protected function shouldEnforceHttps(): bool
    {
        // Forcer HTTPS uniquement en production
        // En développement local, on n'impose pas HTTPS même si FORCE_HTTPS est true
        // car Docker n'est pas configuré avec SSL
        return app()->environment('production');
    }
}
