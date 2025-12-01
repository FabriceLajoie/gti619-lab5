<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Log a general security event
     *
     * @param string $eventType
     * @param int|null $userId
     * @param array $details
     * @param Request|null $request
     * @return AuditLog
     */
    public function logSecurityEvent(string $eventType, ?int $userId = null, array $details = [], ?Request $request = null): AuditLog
    {
        $request = $request ?? request();
        
        return AuditLog::create([
            'event_type' => $eventType,
            'user_id' => $userId ?? Auth::id(),
            'ip_address' => $request ? $request->ip() : null,
            'user_agent' => $request ? $request->userAgent() : null,
            'details' => $details,
        ]);
    }

    /**
     * Log successful authentication attempt
     *
     * @param int $userId
     * @param Request|null $request
     * @return AuditLog
     */
    public function logSuccessfulAuthentication(int $userId, ?Request $request = null): AuditLog
    {
        return $this->logSecurityEvent('login_success', $userId, [
            'message' => 'User successfully authenticated'
        ], $request);
    }

    /**
     * Log failed authentication attempt
     *
     * @param string $email
     * @param Request|null $request
     * @return AuditLog
     */
    public function logFailedAuthentication(string $email, ?Request $request = null): AuditLog
    {
        return $this->logSecurityEvent('login_failed', null, [
            'email' => $email,
            'message' => 'Failed authentication attempt'
        ], $request);
    }

    /**
     * Log account lockout event
     *
     * @param int $userId
     * @param int $failedAttempts
     * @param Request|null $request
     * @return AuditLog
     */
    public function logAccountLockout(int $userId, int $failedAttempts, ?Request $request = null): AuditLog
    {
        return $this->logSecurityEvent('account_locked', $userId, [
            'failed_attempts' => $failedAttempts,
            'message' => 'Account locked due to excessive failed login attempts'
        ], $request);
    }

    /**
     * Log account unlock event
     *
     * @param int $userId
     * @param int $unlockedByUserId
     * @param Request|null $request
     * @return AuditLog
     */
    public function logAccountUnlock(int $userId, int $unlockedByUserId, ?Request $request = null): AuditLog
    {
        return $this->logSecurityEvent('account_unlocked', $userId, [
            'unlocked_by_user_id' => $unlockedByUserId,
            'message' => 'Account unlocked by administrator'
        ], $request);
    }

    /**
     * Log password change event
     *
     * @param int $userId
     * @param bool $forced
     * @param Request|null $request
     * @return AuditLog
     */
    public function logPasswordChange(int $userId, bool $forced = false, ?Request $request = null): AuditLog
    {
        return $this->logSecurityEvent('password_changed', $userId, [
            'forced' => $forced,
            'message' => $forced ? 'Password changed (forced)' : 'Password changed by user'
        ], $request);
    }

    /**
     * Log password policy violation
     *
     * @param int $userId
     * @param array $violations
     * @param Request|null $request
     * @return AuditLog
     */
    public function logPasswordPolicyViolation(int $userId, array $violations, ?Request $request = null): AuditLog
    {
        return $this->logSecurityEvent('password_policy_violation', $userId, [
            'violations' => $violations,
            'message' => 'Password policy violation detected'
        ], $request);
    }

    /**
     * Log role change event
     *
     * @param int $userId
     * @param string $oldRole
     * @param string $newRole
     * @param int $changedByUserId
     * @param Request|null $request
     * @return AuditLog
     */
    public function logRoleChange(int $userId, string $oldRole, string $newRole, int $changedByUserId, ?Request $request = null): AuditLog
    {
        return $this->logSecurityEvent('role_changed', $userId, [
            'old_role' => $oldRole,
            'new_role' => $newRole,
            'changed_by_user_id' => $changedByUserId,
            'message' => "Role changed from {$oldRole} to {$newRole}"
        ], $request);
    }

    /**
     * Log security configuration change
     *
     * @param int $userId
     * @param array $changes
     * @param Request|null $request
     * @return AuditLog
     */
    public function logSecurityConfigChange(int $userId, array $changes, ?Request $request = null): AuditLog
    {
        return $this->logSecurityEvent('security_config_changed', $userId, [
            'changes' => $changes,
            'message' => 'Security configuration updated'
        ], $request);
    }

    /**
     * Log unauthorized access attempt
     *
     * @param string $resource
     * @param string $action
     * @param int|null $userId
     * @param Request|null $request
     * @return AuditLog
     */
    public function logUnauthorizedAccess(string $resource, string $action, ?int $userId = null, ?Request $request = null): AuditLog
    {
        return $this->logSecurityEvent('unauthorized_access', $userId, [
            'resource' => $resource,
            'action' => $action,
            'message' => "Unauthorized access attempt to {$resource}:{$action}"
        ], $request);
    }

    /**
     * Log user creation event
     *
     * @param int $newUserId
     * @param int $createdByUserId
     * @param string $role
     * @param Request|null $request
     * @return AuditLog
     */
    public function logUserCreation(int $newUserId, int $createdByUserId, string $role, ?Request $request = null): AuditLog
    {
        return $this->logSecurityEvent('user_created', $newUserId, [
            'created_by_user_id' => $createdByUserId,
            'role' => $role,
            'message' => "New user created with role: {$role}"
        ], $request);
    }

    /**
     * Log session-related security events
     *
     * @param string $sessionEvent
     * @param int|null $userId
     * @param array $details
     * @param Request|null $request
     * @return AuditLog
     */
    public function logSessionEvent(string $sessionEvent, ?int $userId = null, array $details = [], ?Request $request = null): AuditLog
    {
        return $this->logSecurityEvent("session_{$sessionEvent}", $userId, array_merge([
            'message' => "Session event: {$sessionEvent}"
        ], $details), $request);
    }
}