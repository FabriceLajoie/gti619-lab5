<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\AuditLogger;

class RoleMiddleware
{
    /**
     * Get the audit logger instance
     */
    protected function getAuditLogger()
    {
        return app(AuditLogger::class);
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        
        // Load user's role if not already loaded
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        // Check if user has a role assigned
        if (!$user->role) {
            $this->getAuditLogger()->logSecurityEvent('unauthorized_access_no_role', $user->id, [
                'route' => $request->route()->getName(),
                'url' => $request->url(),
                'required_roles' => $roles
            ], $request);
            
            return redirect()->route('dashboard')->with('error', 'Access denied: No role assigned.');
        }

        $userRole = $user->role->name;

        // Check if user's role is in the allowed roles
        if (!in_array($userRole, $roles)) {
            $this->getAuditLogger()->logSecurityEvent('unauthorized_access_insufficient_role', $user->id, [
                'user_role' => $userRole,
                'required_roles' => $roles,
                'route' => $request->route()->getName(),
                'url' => $request->url()
            ], $request);

            // Redirect based on user's role to appropriate page
            $redirectRoute = $this->getRedirectRouteForRole($userRole);
            return redirect()->route($redirectRoute)->with('error', 'Access denied: Insufficient permissions.');
        }

        // Log successful access
        $this->getAuditLogger()->logSecurityEvent('authorized_access', $user->id, [
            'user_role' => $userRole,
            'route' => $request->route()->getName(),
            'url' => $request->url()
        ], $request);

        return $next($request);
    }

    /**
     * Get appropriate redirect route based on user's role
     *
     * @param string $role
     * @return string
     */
    protected function getRedirectRouteForRole($role)
    {
        switch ($role) {
            case 'Administrateur':
                return 'dashboard';
            case 'Préposé aux clients résidentiels':
                return 'clients.residential';
            case 'Préposé aux clients d\'affaire':
                return 'clients.business';
            default:
                return 'dashboard';
        }
    }
}