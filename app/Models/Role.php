<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;
    
    protected $fillable = ['name', 'description'];

    /**
     * Get the users that have this role.
     */
    public function users() 
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the permissions for this role.
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /**
     * Check if role has a specific permission.
     *
     * @param string $permission
     * @return bool
     */
    public function hasPermission($permission)
    {
        return $this->permissions()->where('name', $permission)->exists();
    }

    /**
     * Check if role has permission for a resource and action.
     *
     * @param string $resource
     * @param string $action
     * @return bool
     */
    public function hasPermissionFor($resource, $action)
    {
        return $this->permissions()
            ->where('resource', $resource)
            ->where('action', $action)
            ->exists();
    }
}
