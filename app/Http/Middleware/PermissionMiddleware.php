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
     * Handle an incoming request
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$permissions
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        
        // Load user's role and permissions if not already loaded
        if (!$user->relationLoaded('role')) {
            $user->load('role.permissions');
        }

        // Check if user has role assigned
        if (!$user->role) {
            $this->auditLogger->logSecurityEvent('unauthorized_access_no_role', $user->id, [
                'route' => $request->route()->getName(),
                'url' => $request->url(),
                'required_permissions' => $permissions
            ]);
            
            return redirect()->route('dashboard')->with('error', 'Access denied: No role assigned.');
        }

        // Check if user has any of the required permissions
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

            // Redirect based on user's role to appropriate page
            $redirectRoute = $this->getRedirectRouteForRole($user->role->name);
            return redirect()->route($redirectRoute)->with('error', 'Access denied: Insufficient permissions.');
        }

        // Log access
        $this->auditLogger->logSecurityEvent('authorized_access', $user->id, [
            'user_role' => $user->role->name,
            'route' => $request->route()->getName(),
            'url' => $request->url(),
            'matched_permissions' => array_intersect($permissions, $user->role->permissions->pluck('name')->toArray())
        ]);

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