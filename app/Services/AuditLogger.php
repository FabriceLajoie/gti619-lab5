<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Enregistrer un événement de sécurité général
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
     * Enregistrer une tentative d'auth réussie
     *
     * @param int $userId
     * @param Request|null $request
     * @return AuditLog
     */
    public function logSuccessfulAuthentication(int $userId, ?Request $request = null): AuditLog
    {
        return $this->logSecurityEvent('login_success', $userId, [
            'message' => 'Utilisateur authentifié avec succès'
        ], $request);
    }

    /**
     * Enregistrer une tentative d'auth échouée
     *
     * @param string $email
     * @param Request|null $request
     * @return AuditLog
     */
    public function logFailedAuthentication(string $email, ?Request $request = null): AuditLog
    {
        return $this->logSecurityEvent('login_failed', null, [
            'email' => $email,
            'message' => 'Tentative d\'authentification échouée'
        ], $request);
    }

    /**
     * Enregistrer un verrouillage de compte
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
            'message' => 'Compte verrouillé en raison de tentatives de connexion échouées excessives'
        ], $request);
    }

    /**
     * Enregistre déverrouillage de compte
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
            'message' => 'Compte déverrouillé par l\'administrateur'
        ], $request);
    }

    /**
     * Enregistrer changement de mot de passe
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
            'message' => $forced ? 'Mot de passe changé (forcé)' : 'Mot de passe changé par l\'utilisateur'
        ], $request);
    }

    /**
     * Enregistrer une violation de politique de mdp
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
            'message' => 'Violation de politique de mot de passe détectée'
        ], $request);
    }

    /**
     * Enregistrer changement de rôle
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
            'message' => "Rôle changé de {$oldRole} à {$newRole}"
        ], $request);
    }

    /**
     * Enregistrer config de sécurité
     *
     * @param int $userId
     * @param array $oldConfig
     * @param array $newConfig
     * @param Request|null $request
     * @param string|null $message
     * @return AuditLog
     */
    public function logSecurityConfigChange(int $userId, array $oldConfig, array $newConfig, ?Request $request = null, ?string $message = null): AuditLog
    {
        $changes = [];
        foreach ($newConfig as $key => $newValue) {
            $oldValue = $oldConfig[$key] ?? null;
            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue
                ];
            }
        }

        return $this->logSecurityEvent('security_config_changed', $userId, [
            'changes' => $changes,
            'message' => $message ?? 'Configuration de sécurité mise à jour'
        ], $request);
    }

    /**
     * Enregistrer try accès non autorisé
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
            'message' => "Tentative d'accès non autorisé à {$resource}:{$action}"
        ], $request);
    }

    /**
     * Enregistrer de création d'utilisateur
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
            'message' => "Nouvel utilisateur créé avec le rôle: {$role}"
        ], $request);
    }

    /**
     * Enregistrer affaires de session
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
            'message' => "Événement de session: {$sessionEvent}"
        ], $details), $request);
    }
}