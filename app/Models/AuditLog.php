<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    /**
     * Les attributs qui sont assignables en masse
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
     * Les attributs qui doivent être convertis en types natifs
     *
     * @var array
     */
    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Obtenir l'utilisateur associé à cette entrée de journal d'audit
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Portée pour filtrer par type d'événement
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
     * Portée pour filtrer par utilisateur
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
     * Portée pour filtrer par plage de dates
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
     * Portée pour les journaux récents
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
     * Portée pour les journaux liés à l'authentification
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
     * Portée pour les journaux liés à la sécurité
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
     * Obtenir le type d'événement formaté pour l'affichage
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
     * Obtenir le niveau de sévérité
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
     * Obtenir la classe CSS pour le niveau de sévérité
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