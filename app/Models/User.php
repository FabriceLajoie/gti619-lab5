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
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'password_hash',
        'password_salt',
        'failed_login_attempts',
        'locked_until',
        'must_change_password',
        'role_id',
    ];

    protected $dates = [
    'email_verified_at',
    'password_changed_at',
    ];

    /**
     * The attributes that should be hidden for arrays.
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
     * The attributes that should be cast to native types.
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
     * Get the password history for the user.
     */
    public function passwordHistories()
    {
        return $this->hasMany(\App\Models\PasswordHistory::class);
    }
    
    /**
     * Get the user's role (single role relationship).
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
