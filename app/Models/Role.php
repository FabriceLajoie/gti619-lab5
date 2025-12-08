<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;
    
    protected $fillable = ['name', 'description'];

    /**
     * Obtenir les utilisateurs qui ont ce rôle
     */
    public function users() 
    {
        return $this->hasMany(User::class);
    }

    /**
     * Obtenir les permissions pour ce rôle
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /**
     * Vérifier si le rôle a une permission spécifique
     *
     * @param string $permission
     * @return bool
     */
    public function hasPermission($permission)
    {
        return $this->permissions()->where('name', $permission)->exists();
    }

    /**
     * Vérifier si le rôle a la permission
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
