<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecureHeaders
{
    /**
     * Traite une requête entrante et ajoute les en-têtes de sécurité.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Empêche les attaques de clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');
        
        // Empêche le MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // Active la protection XSS dans les navigateurs
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        
        // Contrôle les informations de référence
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Content Security Polic restreint le chargement des ressources
        $response->headers->set('Content-Security-Policy', 
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
            "style-src 'self' 'unsafe-inline'; " .
            "img-src 'self' data:; " .
            "font-src 'self'; " .
            "connect-src 'self'; " .
            "frame-ancestors 'none'"
        );
        
        // Permissions Policy restreint les fonctionnalités du navigateur
        $response->headers->set('Permissions-Policy', 
            'geolocation=(), microphone=(), camera=(), payment=()'
        );

        return $response;
    }
}
