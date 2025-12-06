<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\AuditLogger;

class RoleMiddleware
{
    protected $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    /**
     * Gérer une requête entrante
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        // Vérifier si l'utilisateur est authentifié
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        
        // Charger le rôle de l'utilisateur s'il n'est pas déjà chargé
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        // Vérifier si l'utilisateur a un rôle assigné
        if (!$user->role) {
            $this->auditLogger->logSecurityEvent('unauthorized_access_no_role', $user->id, [
                'route' => $request->route()->getName(),
                'url' => $request->url(),
                'required_roles' => $roles
            ], $request);
            
            return redirect()->route('dashboard')->with('error', 'Access denied: No role assigned.');
        }

        $userRole = $user->role->name;

        // Vérifier si le rôle de l'utilisateur est dans les rôles autorisés
        if (!in_array($userRole, $roles)) {
            $this->auditLogger->logSecurityEvent('unauthorized_access_insufficient_role', $user->id, [
                'user_role' => $userRole,
                'required_roles' => $roles,
                'route' => $request->route()->getName(),
                'url' => $request->url()
            ], $request);

            // Rediriger selon le rôle de l'utilisateur vers la page appropriée
            $redirectRoute = $this->getRedirectRouteForRole($userRole);
            return redirect()->route($redirectRoute)->with('error', 'Accès refusé: Permissions insuffisantes.');
        }

        // Enregistrer l'accès
        $this->auditLogger->logSecurityEvent('authorized_access', $user->id, [
            'user_role' => $userRole,
            'route' => $request->route()->getName(),
            'url' => $request->url()
        ], $request);

        return $next($request);
    }

    /**
     * Obtenir la route de redirection appropriée selon le rôle
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