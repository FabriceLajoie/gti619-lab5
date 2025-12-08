<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\ReauthenticationController;
use Carbon\Carbon;

class SessionSecurityService
{
    protected $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    /**
     * Valide la session pour les opérations critiques
     *
     * @param Request $request
     * @param array $options
     * @return array
     */
    public function validateSession(Request $request, array $options = []): array
    {
        $user = Auth::user();
        $sessionId = Session::getId();
        $currentTime = Carbon::now();
        
        $validation = [
            'valid' => true,
            'warnings' => [],
            'errors' => []
        ];

        // Vérifier si l'utilisateur est authentifié
        if (!$user) {
            $validation['valid'] = false;
            $validation['errors'][] = 'Utilisateur non authentifié';
            return $validation;
        }

        // Valider l'empreinte de session
        $this->validateSessionFingerprint($request, $validation, $options);

        // Vérifier l'âge de la session
        $this->validateSessionAge($request, $validation, $options);

        // Vérifier les sessions concurrentes si activé
        if ($options['check_concurrent_sessions'] ?? false) {
            $this->validateConcurrentSessions($user, $validation, $options);
        }

        // Vérifier l'exigence de ré-authentification
        if ($options['require_reauth'] ?? false) {
            $this->validateReauthentication($validation, $options);
        }

        // Enregistrer le résultat de validation
        $this->auditLogger->logSecurityEvent('session_validation', $user->id, [
            'session_id' => substr($sessionId, 0, 8) . '...',
            'validation_result' => $validation['valid'] ? 'passed' : 'failed',
            'warnings' => $validation['warnings'],
            'errors' => $validation['errors'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ], $request);

        return $validation;
    }

    /**
     * Valide l'empreinte de session
     *
     * @param Request $request
     * @param array &$validation
     * @param array $options
     * @return void
     */
    protected function validateSessionFingerprint(Request $request, array &$validation, array $options): void
    {
        $storedIp = Session::get('session_ip');
        $storedUserAgent = Session::get('session_user_agent');
        $currentIp = $request->ip();
        $currentUserAgent = $request->userAgent();

        // Stocker l'empreinte si elle n'existe pas
        if (!$storedIp || !$storedUserAgent) {
            Session::put('session_ip', $currentIp);
            Session::put('session_user_agent', $currentUserAgent);
            return;
        }

        // Vérifier changement d'adresse IP
        if ($storedIp !== $currentIp) {
            if ($options['strict_ip_validation'] ?? true) {
                $validation['valid'] = false;
                $validation['errors'][] = 'Incompatibilité d\'adresse IP de session';
            } else {
                $validation['warnings'][] = 'Adresse IP changée pendant la session';
            }
        }

        // Vérifier changement de User-Agent
        if ($storedUserAgent !== $currentUserAgent) {
            if ($options['strict_ua_validation'] ?? true) {
                $validation['valid'] = false;
                $validation['errors'][] = 'Incompatibilité de User-Agent de session';
            } else {
                $validation['warnings'][] = 'User-Agent changé pendant la session';
            }
        }
    }

    /**
     * Valide l'âge de la session
     *
     * @param Request $request
     * @param array &$validation
     * @param array $options
     * @return void
     */
    protected function validateSessionAge(Request $request, array &$validation, array $options): void
    {
        $sessionStart = Session::get('session_start_time');
        $maxAge = $options['max_session_age'] ?? 480; // 8 heures par défaut

        if (!$sessionStart) {
            Session::put('session_start_time', Carbon::now()->toISOString());
            return;
        }

        $sessionAge = Carbon::now()->diffInMinutes(Carbon::parse($sessionStart));
        
        if ($sessionAge > $maxAge) {
            $validation['valid'] = false;
            $validation['errors'][] = 'La session a expiré en raison de l\'âge';
        } elseif ($sessionAge > ($maxAge * 0.8)) {
            $validation['warnings'][] = 'La session approche de l\'expiration';
        }
    }

    /**
     * Valide les sessions concurrentes
     *
     * @param $user
     * @param array &$validation
     * @param array $options
     * @return void
     */
    protected function validateConcurrentSessions($user, array &$validation, array $options): void
    {
        $maxConcurrentSessions = $options['max_concurrent_sessions'] ?? 3;
        
        // Ceci nécessiterait une table de sessions pour suivre les sessions actives
        // Pour l'instant, nous ajoutons juste un avertissement
        $validation['warnings'][] = 'Validation des sessions concurrentes non entièrement implémentée';
    }

    /**
     * Valide l'exigence de ré-authentification
     *
     * @param array &$validation
     * @param array $options
     * @return void
     */
    protected function validateReauthentication(array &$validation, array $options): void
    {
        $maxAge = $options['reauth_max_age'] ?? 15; // 15 minutes par défaut
        
        if (ReauthenticationController::needsReauth($maxAge)) {
            $validation['valid'] = false;
            $validation['errors'][] = 'Ré-authentification requise pour cette opération';
        }
    }

    /**
     * Régénère la session pour la sécurité
     *
     * @param Request $request
     * @param bool $preserveFingerprint
     * @return void
     */
    public function regenerateSession(Request $request, bool $preserveFingerprint = true): void
    {
        $user = Auth::user();
        
        // Stocker les données de session actuelles si nécessaire
        $sessionData = [];
        if ($preserveFingerprint) {
            $sessionData = [
                'session_ip' => Session::get('session_ip', $request->ip()),
                'session_user_agent' => Session::get('session_user_agent', $request->userAgent()),
                'session_start_time' => Session::get('session_start_time', Carbon::now()->toISOString()),
                'session_fingerprint_initialized' => true,
            ];
        } else {
            // Nouvelle empreinte de session
            $sessionData = [
                'session_ip' => $request->ip(),
                'session_user_agent' => $request->userAgent(),
                'session_start_time' => Carbon::now()->toISOString(),
                'session_fingerprint_initialized' => true,
            ];
        }

        // Régénérer l'ID de session
        Session::regenerate();

        // Restaurer les données de session
        foreach ($sessionData as $key => $value) {
            Session::put($key, $value);
        }

        // Enregistrer la régénération de session
        if ($user) {
            $this->auditLogger->logSecurityEvent('session_regenerated', $user->id, [
                'message' => 'Session régénérée pour la sécurité',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ], $request);
        }
    }

    /**
     * Nettoie les sessions expirées
     *
     * @return int Nombre de sessions nettoyées
     */
    public function cleanupExpiredSessions(): int
    {
        // Cette méthode sera appelée par une tâche planifiée
        // Laravel gère automatiquement le nettoyage des sessions via le garbage collector
        // Mais nous pouvons ajouter une logique personnalisée ici si nécessaire
        
        $lifetime = config('session.lifetime', 120);
        $expiredTime = Carbon::now()->subMinutes($lifetime)->timestamp;
        
        // Nettoyer les sessions expirées de la base de données
        $deleted = \DB::table(config('session.table', 'sessions'))
            ->where('last_activity', '<', $expiredTime)
            ->delete();
        
        return $deleted;
    }

    /**
     * Initialise l'empreinte de session lors de la connexion
     *
     * @param Request $request
     * @return void
     */
    public function initializeSessionFingerprint(Request $request): void
    {
        Session::put('session_ip', $request->ip());
        Session::put('session_user_agent', $request->userAgent());
        Session::put('session_start_time', Carbon::now()->toISOString());
        Session::put('session_fingerprint_initialized', true);
    }

    /**
     * Invalide la session pour des raisons de sécurité
     *
     * @param Request $request
     * @param string $reason
     * @return void
     */
    public function invalidateSession(Request $request, string $reason = 'Violation de sécurité'): void
    {
        $user = Auth::user();
        $sessionId = Session::getId();

        // Enregistrer l'invalidation de session
        if ($user) {
            $this->auditLogger->logSecurityEvent('session_invalidated', $user->id, [
                'reason' => $reason,
                'session_id' => substr($sessionId, 0, 8) . '...',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ], $request);
        }

        // Invalider la session
        Session::invalidate();
        Session::regenerateToken();
        
        // Déconnecter l'utilisateur
        Auth::logout();
    }
}