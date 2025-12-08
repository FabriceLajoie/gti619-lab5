<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Services\SessionSecurityService;
use Carbon\Carbon;

class SessionSecurityMiddleware
{
    protected $sessionSecurityService;

    public function __construct(SessionSecurityService $sessionSecurityService)
    {
        $this->sessionSecurityService = $sessionSecurityService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return $next($request);
        }

        // Initialize session fingerprint on first request
        if (!Session::has('session_fingerprint_initialized')) {
            $this->initializeSessionFingerprint($request);
        }

        // Validate session fingerprint
        if (!$this->validateSessionFingerprint($request)) {
            // Session fingerprint mismatch - potential hijacking
            $this->sessionSecurityService->invalidateSession($request, 'Session fingerprint mismatch');
            
            return redirect()->route('login')->withErrors([
                'session' => 'Your session has been terminated for security reasons. Please log in again.'
            ]);
        }

        // Update last activity timestamp
        Session::put('last_activity', Carbon::now()->timestamp);

        return $next($request);
    }

    /**
     * Initialize session fingerprint
     *
     * @param Request $request
     * @return void
     */
    protected function initializeSessionFingerprint(Request $request): void
    {
        Session::put('session_ip', $request->ip());
        Session::put('session_user_agent', $request->userAgent());
        Session::put('session_fingerprint_initialized', true);
    }
    /**
     * Validate session fingerprint
     *
     * @param Request $request
     * @return bool
     */
    protected function validateSessionFingerprint(Request $request): bool
    {
        $storedIp = Session::get('session_ip');
        $storedUserAgent = Session::get('session_user_agent');
        $currentIp = $request->ip();
        $currentUserAgent = $request->userAgent();

        // Check IP address match
        if ($storedIp && $storedIp !== $currentIp) {
            return false;
        }

        // Check User-Agent match
        if ($storedUserAgent && $storedUserAgent !== $currentUserAgent) {
            return false;
        }

        return true;
    }
}
