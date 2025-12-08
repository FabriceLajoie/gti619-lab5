<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\AuditLogger;
use App\Services\PermissionService;

class PermissionMiddleware
{
    protected $auditLogger;
    protected $permissionService;

    public function __construct(AuditLogger $auditLogger, PermissionService $permissionService)
    {
        $this->auditLogger = $auditLogger;
        $this->permissionService = $permissionService;
    }

    /**
     * Gérer une requête entrante
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$permissions
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        // Vérifier si l'utilisateur est authentifié
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        
        // Charger le rôle et les permissions de l'utilisateur s'ils ne sont pas déjà chargés
        if (!$user->relationLoaded('role')) {
            $user->load('role.permissions');
        }

        // Vérifier si l'utilisateur a un rôle assigné
        if (!$user->role) {
            $this->auditLogger->logSecurityEvent('unauthorized_access_no_role', $user->id, [
                'route' => $request->route()->getName(),
                'url' => $request->url(),
                'required_permissions' => $permissions
            ]);
            
            return redirect()->route('dashboard')->with('error', 'Access denied: No role assigned.');
        }

        // Vérifier si l'utilisateur a une des permissions requises
        $hasPermission = false;
        foreach ($permissions as $permission) {
            if ($this->permissionService->userHasPermission($user, $permission)) {
                $hasPermission = true;
                break;
            }
        }

        if (!$hasPermission) {
            $this->auditLogger->logSecurityEvent('unauthorized_access_insufficient_permissions', $user->id, [
                'user_role' => $user->role->name,
                'user_permissions' => $user->role->permissions->pluck('name')->toArray(),
                'required_permissions' => $permissions,
                'route' => $request->route()->getName(),
                'url' => $request->url()
            ]);

            // Rediriger selon le rôle de l'utilisateur vers page appropriée
            $redirectRoute = $this->getRedirectRouteForRole($user->role->name);
            return redirect()->route($redirectRoute)->with('error', 'Accès refusé: Permissions insuffisantes.');
        }

        // Enregistrer l'accès
        $this->auditLogger->logSecurityEvent('authorized_access', $user->id, [
            'user_role' => $user->role->name,
            'route' => $request->route()->getName(),
            'url' => $request->url(),
            'matched_permissions' => array_intersect($permissions, $user->role->permissions->pluck('name')->toArray())
        ]);

        return $next($request);
    }

    /**
     * Obtenir la route de redirection appropriée selon le rôle utilisateur
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