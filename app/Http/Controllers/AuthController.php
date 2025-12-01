<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Services\PBKDF2PasswordHasher;
use App\Services\SecurityConfigService;
use App\Services\AuditLogger;
use Carbon\Carbon;

class AuthController extends Controller
{
    protected $passwordHasher;
    protected $securityConfigService;
    protected $auditLogger;

    public function __construct(
        PBKDF2PasswordHasher $passwordHasher,
        SecurityConfigService $securityConfigService,
        AuditLogger $auditLogger
    ) {
        $this->passwordHasher = $passwordHasher;
        $this->securityConfigService = $securityConfigService;
        $this->auditLogger = $auditLogger;
    }

    /**
     * Show the login form
     */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Handle login request with enhanced security controls
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = $credentials['email'];
        $password = $credentials['password'];

        // Find user by email
        $user = User::where('email', $email)->first();

        if (!$user) {
            // Log failed attempt for non-existent user
            $this->auditLogger->logFailedAuthentication($email, $request);
            
            // Apply progressive delay even for non-existent users to prevent enumeration
            $this->applyProgressiveDelay(1);
            
            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])->onlyInput('email');
        }

        // Check if account is locked
        if ($this->isAccountLocked($user)) {
            $this->auditLogger->logFailedAuthentication($email, $request);
            
            return back()->withErrors([
                'email' => 'Account is temporarily locked due to too many failed attempts. Please try again later.',
            ])->onlyInput('email');
        }

        // Attempt authentication using custom PBKDF2 provider
        if (Auth::attempt($credentials)) {
            // Reset failed attempts on successful login
            $user->failed_login_attempts = 0;
            $user->locked_until = null;
            $user->save();

            // Log successful authentication
            $this->auditLogger->logSuccessfulAuthentication($user->id, $request);

            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        } else {
            // Handle failed authentication
            $this->handleFailedAuthentication($user, $request);
            
            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])->onlyInput('email');
        }
    }

    /**
     * Check if account is locked
     */
    protected function isAccountLocked(User $user): bool
    {
        if (!$user->locked_until) {
            return false;
        }

        // Check if lock period has expired
        if (Carbon::now()->greaterThan($user->locked_until)) {
            // Unlock account automatically
            $user->locked_until = null;
            $user->failed_login_attempts = 0;
            $user->save();
            return false;
        }

        return true;
    }

    /**
     * Handle failed authentication attempt
     */
    protected function handleFailedAuthentication(User $user, Request $request): void
    {
        $securityConfig = $this->securityConfigService->getConfig();
        
        // Increment failed attempts
        $user->failed_login_attempts = ($user->failed_login_attempts ?? 0) + 1;
        
        // Log failed attempt
        $this->auditLogger->logFailedAuthentication($user->email, $request);

        // Check if account should be locked
        if ($user->failed_login_attempts >= $securityConfig->max_login_attempts) {
            $lockoutDuration = $securityConfig->lockout_duration; // in minutes
            $user->locked_until = Carbon::now()->addMinutes($lockoutDuration);
            
            // Log account lockout
            $this->auditLogger->logAccountLockout($user->id, $user->failed_login_attempts, $request);
        }

        $user->save();

        // Apply progressive delay based on failed attempts
        $this->applyProgressiveDelay($user->failed_login_attempts);
    }

    /**
     * Apply progressive delay to prevent brute force attacks
     */
    protected function applyProgressiveDelay(int $failedAttempts): void
    {
        // Progressive delay: 1s, 2s, 4s, 8s, 16s (max 16 seconds)
        $delay = min(pow(2, $failedAttempts - 1), 16);
        sleep($delay);
    }

    /**
     * Show dashboard
     */
    public function showDashboard()
    {
        return view('dashboard');
    }



    /**
     * Handle logout
     */
    public function logout(Request $request)
    {
        $userId = Auth::id();
        
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Log logout event
        if ($userId) {
            $this->auditLogger->logSecurityEvent('user_logout', $userId, [
                'message' => 'User logged out'
            ], $request);
        }

        return redirect('/');
    }
}
