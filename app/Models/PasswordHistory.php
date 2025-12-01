<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordHistory extends Model
{
    use HasFactory;
    
    /**
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'password_hash',
        'salt',
        'iterations',
        'algorithm'
    ];
    
    /**
     * The attributes that should be hidden for array
     *
     * @var array
     */
    protected $hidden = [
        'password_hash',
        'salt'
    ];
    
    /**
     * native type
     *
     * @var array
     */
    protected $casts = [
        'iterations' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    /**
     * Get the user that owns the password history
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
