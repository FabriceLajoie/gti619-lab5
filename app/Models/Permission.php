<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'resource',
        'action',
        'description'
    ];

    /**
     * Obtenir les rôles qui ont cette permission
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}