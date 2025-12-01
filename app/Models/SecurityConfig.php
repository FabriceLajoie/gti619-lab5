<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'max_login_attempts',
        'lockout_duration_minutes',
        'password_min_length',
        'password_require_uppercase',
        'password_require_lowercase',
        'password_require_numbers',
        'password_require_special',
        'password_history_count',
        'password_expiry_days',
        'pbkdf2_iterations',
        'session_timeout_minutes'
    ];

    protected $casts = [
        'max_login_attempts' => 'integer',
        'lockout_duration_minutes' => 'integer',
        'password_min_length' => 'integer',
        'password_require_uppercase' => 'boolean',
        'password_require_lowercase' => 'boolean',
        'password_require_numbers' => 'boolean',
        'password_require_special' => 'boolean',
        'password_history_count' => 'integer',
        'password_expiry_days' => 'integer',
        'pbkdf2_iterations' => 'integer',
        'session_timeout_minutes' => 'integer'
    ];

    protected $attributes = [
        'max_login_attempts' => 5,
        'lockout_duration_minutes' => 30,
        'password_min_length' => 12,
        'password_require_uppercase' => true,
        'password_require_lowercase' => true,
        'password_require_numbers' => true,
        'password_require_special' => true,
        'password_history_count' => 5,
        'password_expiry_days' => 90,
        'pbkdf2_iterations' => 100000,
        'session_timeout_minutes' => 120
    ];

    /**
     * Get the singleton security configuration instance
     */
    public static function getInstance()
    {
        return static::first() ?? static::create([]);
    }

    /**
     * Validation rules for security configuration
     */
    public static function validationRules()
    {
        return [
            'max_login_attempts' => 'sometimes|integer|min:1|max:20',
            'lockout_duration_minutes' => 'sometimes|integer|min:1|max:1440', // Max 24 hours
            'password_min_length' => 'sometimes|integer|min:8|max:128',
            'password_require_uppercase' => 'sometimes|boolean',
            'password_require_lowercase' => 'sometimes|boolean',
            'password_require_numbers' => 'sometimes|boolean',
            'password_require_special' => 'sometimes|boolean',
            'password_history_count' => 'sometimes|integer|min:0|max:50',
            'password_expiry_days' => 'sometimes|integer|min:0|max:365',
            'pbkdf2_iterations' => 'sometimes|integer|min:10000|max:1000000',
            'session_timeout_minutes' => 'sometimes|integer|min:5|max:1440'
        ];
    }

    /**
     * Get validation messages
     */
    public static function validationMessages()
    {
        return [
            'max_login_attempts.min' => 'Maximum login attempts must be at least 1',
            'max_login_attempts.max' => 'Maximum login attempts cannot exceed 20',
            'lockout_duration_minutes.min' => 'Lockout duration must be at least 1 minute',
            'lockout_duration_minutes.max' => 'Lockout duration cannot exceed 24 hours',
            'password_min_length.min' => 'Password minimum length must be at least 8 characters',
            'password_min_length.max' => 'Password minimum length cannot exceed 128 characters',
            'password_history_count.max' => 'Password history count cannot exceed 50',
            'password_expiry_days.max' => 'Password expiry cannot exceed 365 days',
            'pbkdf2_iterations.min' => 'PBKDF2 iterations must be at least 10,000 for security',
            'pbkdf2_iterations.max' => 'PBKDF2 iterations cannot exceed 1,000,000 for performance',
            'session_timeout_minutes.min' => 'Session timeout must be at least 5 minutes',
            'session_timeout_minutes.max' => 'Session timeout cannot exceed 24 hours'
        ];
    }
}