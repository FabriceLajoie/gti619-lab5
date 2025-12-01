<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogger
{
    /**
     * Log a security event
     *
     * @param string $eventType
     * @param int|null $userId
     * @param array $details
     * @param Request|null $request
     * @return AuditLog
     */
    public function logSecurityEvent(string $eventType, ?int $userId = null, array $details = [], ?Request $request = null): AuditLog
    {
        $request = $request ?: request();
        
        return AuditLog::create([
            'event_type' => $eventType,
            'user_id' => $userId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'details' => $details,
        ]);
    }

    /**
     * Log successful authentication
     *
     * @param int $userId
     * @param Request|null $request
     * @return AuditLog
     */
    public function logSuccessfulAuthentication(int $userId, ?Request $request = null): AuditLog
    {
        return $this->logSecurityEvent('authentication_success', $userId, [
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
        return $this->logSecurityEvent('authentication_failed', null, [
            'email' => $email,
            'message' => 'Failed authentication attempt'
        ], $request);
    }

    /**
     * Log account lockout
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
     * Log account unlock
     *
     * @param int $userId
     * @param int $unlockedBy
     * @param Request|null $request
     * @return AuditLog
     */
    public function logAccountUnlock(int $userId, int $unlockedBy, ?Request $request = null): AuditLog
    {
        return $this->logSecurityEvent('account_unlocked', $userId, [
            'unlocked_by' => $unlockedBy,
            'message' => 'Account unlocked by administrator'
        ], $request);
    }

    /**
     * Log password change
     *
     * @param int $userId
     * @param Request|null $request
     * @return AuditLog
     */
    public function logPasswordChange(int $userId, ?Request $request = null): AuditLog
    {
        return $this->logSecurityEvent('password_changed', $userId, [
            'message' => 'User password changed'
        ], $request);
    }
}