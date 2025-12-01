<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;

class PermissionService
{
    /**
     * Check if a user has a specific permission
     *
     * @param User $user
     * @param string $permission
     * @return bool
     */
    public function userHasPermission(User $user, $permission)
    {
        if (!$user->role) {
            return false;
        }

        return $user->role->hasPermission($permission);
    }

    /**
     * Check if a user has permission for resource and action
     *
     * @param User $user
     * @param string $resource
     * @param string $action
     * @return bool
     */
    public function userHasPermissionFor(User $user, $resource, $action)
    {
        if (!$user->role) {
            return false;
        }

        return $user->role->hasPermissionFor($resource, $action);
    }

    /**
     * Check if a user has any of the roles
     *
     * @param User $user
     * @param array $roles
     * @return bool
     */
    public function userHasAnyRole(User $user, array $roles)
    {
        if (!$user->role) {
            return false;
        }

        return in_array($user->role->name, $roles);
    }

    /**
     * Get all permissions for a user
     *
     * @param User $user
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserPermissions(User $user)
    {
        if (!$user->role) {
            return collect();
        }

        return $user->role->permissions;
    }

    /**
     * Get default permissions for each role based on requirements
     *
     * @return array
     */
    public function getDefaultRolePermissions()
    {
        return [
            'Administrateur' => [
                'view_dashboard',
                'view_settings',
                'manage_users',
                'manage_security_config',
                'view_residential_clients',
                'view_business_clients',
                'manage_residential_clients',
                'manage_business_clients',
                'view_audit_logs',
                'unlock_accounts'
            ],
            'Préposé aux clients résidentiels' => [
                'view_dashboard',
                'view_residential_clients',
                'manage_residential_clients'
            ],
            'Préposé aux clients d\'affaire' => [
                'view_dashboard',
                'view_business_clients',
                'manage_business_clients'
            ]
        ];
    }

    /**
     * Check if user can access a specific route based on role requirements
     *
     * @param User $user
     * @param string $routeName
     * @return bool
     */
    public function canAccessRoute(User $user, $routeName)
    {
        $routePermissions = $this->getRoutePermissions();
        
        if (!isset($routePermissions[$routeName])) {
            return true;
        }

        $requiredPermissions = $routePermissions[$routeName];
        
        foreach ($requiredPermissions as $permission) {
            if ($this->userHasPermission($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get route to permission mapping
     *
     * @return array
     */
    protected function getRoutePermissions()
    {
        return [
            'dashboard' => ['view_dashboard'],
            'settings' => ['view_settings', 'manage_security_config'],
            'clients.residential' => ['view_residential_clients'],
            'clients.business' => ['view_business_clients'],
            'client.index' => ['view_residential_clients', 'view_business_clients'],
            'client.create' => ['manage_residential_clients', 'manage_business_clients'],
            'client.store' => ['manage_residential_clients', 'manage_business_clients'],
            'client.show' => ['view_residential_clients', 'view_business_clients'],
            'client.edit' => ['manage_residential_clients', 'manage_business_clients'],
            'client.update' => ['manage_residential_clients', 'manage_business_clients'],
            'client.destroy' => ['manage_residential_clients', 'manage_business_clients'],
        ];
    }
}