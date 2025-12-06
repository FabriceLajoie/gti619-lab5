<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ForcePasswordChange
{
    /**
     * Gérer une requête entrante
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle($request, Closure $next)
    {
        $user = Auth::user();
        if ($user) {
            $maxAgeDays = 90;
            $changedAt = $user->password_changed_at ?: $user->created_at;

            if (Carbon::parse($changedAt)->diffInDays(now()) >= $maxAgeDays) {
                if (! $request->is('password/change') && ! $request->is('password/change/*') && ! $request->is('logout')) {
                    return redirect()->route('password.change')->with('warning', 'Vous devez changer votre mot de passe.');
                }
            }
        }

        return $next($request);
    }
}
