<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'password_salt',
        'failed_login_attempts',
        'locked_until',
        'must_change_password',
        'password_changed_at',
        'role_id',
    ];

    protected $dates = [
    'email_verified_at',
    'password_changed_at',
    ];

    /**
     * Les attributs qui doivent être cachés pour les tableaux et JSON
     * Protège les informations sensibles d'authentification
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'password_hash',
        'password_salt',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password_changed_at' => 'datetime',
        'locked_until' => 'datetime',
        'must_change_password' => 'boolean',
        'failed_login_attempts' => 'integer',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }
    
    /**
     * Get the password history for the user
     */
    public function passwordHistories()
    {
        return $this->hasMany(\App\Models\PasswordHistory::class);
    }
    
    /**
     * Get the user's role (single role relationship)
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Check if user has a specific role
     *
     * @param string $roleName
     * @return bool
     */
    public function hasRole($roleName)
    {
        return $this->role && $this->role->name === $roleName;
    }

    /**
     * Check if user has any of the specified roles
     *
     * @param array $roles
     * @return bool
     */
    public function hasAnyRole(array $roles)
    {
        return $this->role && in_array($this->role->name, $roles);
    }

    /**
     * Check if user has a specific permission
     *
     * @param string $permission
     * @return bool
     */
    public function hasPermission($permission)
    {
        return $this->role && $this->role->hasPermission($permission);
    }

    /**
     * Check if user can access a specific resource with an action.
     *
     * @param string $resource
     * @param string $action
     * @return bool
     */
    public function canAccess($resource, $action)
    {
        return $this->role && $this->role->hasPermissionFor($resource, $action);
    }

    /**
     * Get all permissions for this user.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPermissions()
    {
        return $this->role ? $this->role->permissions : collect();
    }

    /**
     * Check if the user account is currently locked.
     *
     * @return bool
     */
    public function isLocked()
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Get the time remaining until the account is unlocked.
     *
     * @return string|null
     */
    public function getLockTimeRemaining()
    {
        if (!$this->isLocked()) {
            return null;
        }

        return $this->locked_until->diffForHumans();
    }

    /**
     * Unlock the user account
     *
     * @return void
     */
    public function unlock()
    {
        $this->update([
            'failed_login_attempts' => 0,
            'locked_until' => null
        ]);
    }
}
