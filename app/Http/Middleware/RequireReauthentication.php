<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;

class RequireReauthentication
{
    /**
     * Traite une requête entrante.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  int  $maxAge  Âge maximum en minutes pour la ré-authentification (défaut: 15)
     * @return mixed
     */
    public function handle(Request $request, Closure $next, int $maxAge = 15)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $lastReauth = Session::get('last_reauth_at');
        $now = Carbon::now();

        // Vérifier si la ré-authentification est requise
        if (!$lastReauth || $now->diffInMinutes(Carbon::parse($lastReauth)) > $maxAge) {
            // Stocker l'URL de destination
            Session::put('url.intended', $request->fullUrl());
            
            // Rediriger vers la page de ré-authentification
            return redirect()->route('reauth.form')->with('warning', 'Veuillez ressaisir votre mot de passe pour continuer.');
        }

        return $next($request);
    }
}