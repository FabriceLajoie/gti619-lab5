<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable
     *
     * @var array
     */
    protected $fillable = [
        'event_type',
        'user_id',
        'ip_address',
        'user_agent',
        'details',
    ];

    /**
     * The attributes that should be cast to native types
     *
     * @var array
     */
    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user associated with this audit log entry
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by event type
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $eventType
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope to filter by user
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by date range
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope for recent logs
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $days
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for authentication-related logs
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAuthenticationEvents($query)
    {
        return $query->whereIn('event_type', [
            'login_success',
            'login_failed',
            'account_locked',
            'account_unlocked',
            'user_logout'
        ]);
    }

    /**
     * Scope for security-related logs
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSecurityEvents($query)
    {
        return $query->whereIn('event_type', [
            'password_changed',
            'password_policy_violation',
            'role_changed',
            'security_config_changed',
            'unauthorized_access',
            'user_created'
        ]);
    }

    /**
     * Get formatted event type for display
     *
     * @return string
     */
    public function getFormattedEventTypeAttribute(): string
    {
        $eventTypes = [
            'login_success' => 'Successful Login',
            'login_failed' => 'Failed Login',
            'account_locked' => 'Account Locked',
            'account_unlocked' => 'Account Unlocked',
            'user_logout' => 'User Logout',
            'password_changed' => 'Password Changed',
            'password_policy_violation' => 'Password Policy Violation',
            'role_changed' => 'Role Changed',
            'security_config_changed' => 'Security Configuration Changed',
            'unauthorized_access' => 'Unauthorized Access',
            'user_created' => 'User Created',
            'session_created' => 'Session Created',
            'session_destroyed' => 'Session Destroyed',
            'session_hijack_detected' => 'Session Hijack Detected',
        ];

        return $eventTypes[$this->event_type] ?? ucwords(str_replace('_', ' ', $this->event_type));
    }

    /**
     * Get the severity level
     *
     * @return string
     */
    public function getSeverityAttribute(): string
    {
        $highSeverity = [
            'account_locked',
            'unauthorized_access',
            'password_policy_violation',
            'session_hijack_detected',
        ];

        $mediumSeverity = [
            'login_failed',
            'password_changed',
            'role_changed',
            'security_config_changed',
            'user_created',
        ];

        if (in_array($this->event_type, $highSeverity)) {
            return 'high';
        } elseif (in_array($this->event_type, $mediumSeverity)) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Get the CSS class for the severity leve
     *
     * @return string
     */
    public function getSeverityCssClassAttribute(): string
    {
        $severityClasses = [
            'high' => 'text-red-600 bg-red-100',
            'medium' => 'text-yellow-600 bg-yellow-100',
            'low' => 'text-green-600 bg-green-100',
        ];

        return $severityClasses[$this->severity] ?? 'text-gray-600 bg-gray-100';
    }
}