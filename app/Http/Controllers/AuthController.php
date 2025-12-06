<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Services\PBKDF2PasswordHasher;
use App\Services\SecurityConfigService;
use App\Services\AuditLogger;
use App\Services\SessionSecurityService;
use Carbon\Carbon;

class AuthController extends Controller
{
    protected $passwordHasher;
    protected $securityConfigService;
    protected $auditLogger;
    protected $sessionSecurityService;

    public function __construct(
        PBKDF2PasswordHasher $passwordHasher,
        SecurityConfigService $securityConfigService,
        AuditLogger $auditLogger,
        SessionSecurityService $sessionSecurityService
    ) {
        $this->passwordHasher = $passwordHasher;
        $this->securityConfigService = $securityConfigService;
        $this->auditLogger = $auditLogger;
        $this->sessionSecurityService = $sessionSecurityService;
    }

    /**
     * Afficher le formulaire de connexion
     */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Gérer la demande de connexion avec des contrôles de sécurité renforcés
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = $credentials['email'];
        $password = $credentials['password'];

        // Trouver l'utilisateur par email
        $user = User::where('email', $email)->first();

        if (!$user) {
            // Enregistrer la tentative échouée pour un utilisateur inexistant
            $this->auditLogger->logFailedAuthentication($email, $request);
            
            // Appliquer un délai progressif pour les utilisateurs inexistants afin de prévenir l'énumération
            $this->applyProgressiveDelay(1);
            
            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])->onlyInput('email');
        }

        // Vérifier si le compte est verrouillé
        if ($this->isAccountLocked($user)) {
            $this->auditLogger->logFailedAuthentication($email, $request);
            
            return back()->withErrors([
                'email' => 'Account is temporarily locked due to too many failed attempts. Please try again later.',
            ])->onlyInput('email');
        }

        // Tenter l'authentification en utilisant le fournisseur PBKDF2
        if (Auth::attempt($credentials)) {
            // Réinitialiser les tentatives échouées lors de la connexion
            $user->failed_login_attempts = 0;
            $user->locked_until = null;
            $user->save();

            // Enregistrer l'authentification réussie
            $this->auditLogger->logSuccessfulAuthentication($user->id, $request);

            // Régénérer la session pour prévenir la fixation de session
            $this->sessionSecurityService->regenerateSession($request, false);
            
            // Initialiser l'empreinte de session
            $this->sessionSecurityService->initializeSessionFingerprint($request);

            return redirect()->intended(route('dashboard'));
        } else {
            // Gérer l'authentification échouée
            $this->handleFailedAuthentication($user, $request);
            
            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])->onlyInput('email');
        }
    }

    /**
     * Vérifier si le compte est verrouillé
     */
    protected function isAccountLocked(User $user): bool
    {
        if (!$user->locked_until) {
            return false;
        }

        // Vérifier si la période de verrouillage a expiré
        if (Carbon::now()->greaterThan($user->locked_until)) {
            // Déverrouiller le compte automatiquement
            $user->locked_until = null;
            $user->failed_login_attempts = 0;
            $user->save();
            return false;
        }

        return true;
    }

    /**
     * Gérer une tentative d'authentification échouée
     */
    protected function handleFailedAuthentication(User $user, Request $request): void
    {
        $securityConfig = $this->securityConfigService->getConfig();
        
        // Incrémenter les tentatives échouées
        $user->failed_login_attempts = ($user->failed_login_attempts ?? 0) + 1;
        
        // Enregistrer la tentative échouée
        $this->auditLogger->logFailedAuthentication($user->email, $request);

        // Vérifier si le compte doit être verrouillé
        if ($user->failed_login_attempts >= $securityConfig->max_login_attempts) {
            $lockoutDuration = $securityConfig->lockout_duration; // en minutes
            $user->locked_until = Carbon::now()->addMinutes($lockoutDuration);
            
            // Enregistrer le verrouillage du compte
            $this->auditLogger->logAccountLockout($user->id, $user->failed_login_attempts, $request);
        }

        $user->save();

        // Appliquer un délai progressif basé sur les tentatives échouées
        $this->applyProgressiveDelay($user->failed_login_attempts);
    }

    /**
     * Appliquer un délai progressif pour prévenir les attaques par force brute
     */
    protected function applyProgressiveDelay(int $failedAttempts): void
    {
        // 1s, 2s, 4s, 8s, 16s (maximum 16 secondes)
        $delay = min(pow(2, $failedAttempts - 1), 16);
        sleep($delay);
    }

    /**
     * Afficher le tableau de bord
     */
    public function showDashboard()
    {
        return view('dashboard');
    }



    /**
     * Gérer la déconnexion
     */
    public function logout(Request $request)
    {
        $userId = Auth::id();
        
        // Enregistrer l'événement de déconnexion avant de détruire la session
        if ($userId) {
            $this->auditLogger->logSecurityEvent('user_logout', $userId, [
                'message' => 'Utilisateur déconnecté'
            ], $request);
        }
        
        Auth::logout();

        // Invalider complètement la session et régénérer le token CSRF
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
