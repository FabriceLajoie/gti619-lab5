<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use App\Services\AuditLogger;
use App\Services\PBKDF2PasswordHasher;
use Carbon\Carbon;

class ReauthenticationController extends Controller
{
    protected $auditLogger;
    protected $passwordHasher;

    public function __construct(AuditLogger $auditLogger, PBKDF2PasswordHasher $passwordHasher)
    {
        $this->middleware('auth');
        $this->auditLogger = $auditLogger;
        $this->passwordHasher = $passwordHasher;
    }

    /**
     * Affiche le formulaire de ré-authentification
     *
     * @return \Illuminate\View\View
     */
    public function showForm()
    {
        return view('auth.reauth');
    }

    /**
     * Traite la demande de ré-authentification
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function authenticate(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = Auth::user();
        
        // Vérifier le mot de passe avec le hacheur PBKDF2
        $isValid = false;
        if ($user->password_salt && $user->password) {
            // Utiliser la vérification PBKDF2
            $isValid = $this->passwordHasher->verify(
                $request->password,
                $user->password_salt,
                $user->password,
                $user->pbkdf2_iterations ?? 100000
            );
        } else {
            // Repli sur le Hash de Laravel pour les mots de passe hérités
            $isValid = Hash::check($request->password, $user->password);
        }

        if (!$isValid) {
            // Enregistrer la tentative de ré-authentification échouée
            $this->auditLogger->logSecurityEvent('reauth_failed', $user->id, [
                'message' => 'Tentative de ré-authentification échouée',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ], $request);

            return back()->withErrors([
                'password' => 'Le mot de passe fourni est incorrect.',
            ]);
        }

        // Définir l'horodatage de ré-authentification
        Session::put('last_reauth_at', Carbon::now()->toISOString());

        // Enregistrer la ré-authentification réussie
        $this->auditLogger->logSecurityEvent('reauth_success', $user->id, [
            'message' => 'Ré-authentification réussie',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ], $request);

        // Rediriger vers l'URL prévue ou le tableau de bord
        $intended = Session::pull('url.intended', route('dashboard'));
        return redirect($intended)->with('success', 'Ré-authentification réussie.');
    }

    /**
     * Vérifie si l'utilisateur a besoin d'une ré-authentification pour une action spécifique
     *
     * @param int $maxAge Âge maximum en minutes
     * @return bool
     */
    public static function needsReauth(int $maxAge = 15): bool
    {
        if (!Auth::check()) {
            return true;
        }

        $lastReauth = Session::get('last_reauth_at');
        if (!$lastReauth) {
            return true;
        }

        $now = Carbon::now();
        return $now->diffInMinutes(Carbon::parse($lastReauth)) > $maxAge;
    }

    /**
     * Force la ré-authentification en effaçant l'horodatage
     *
     * @return void
     */
    public static function forceReauth(): void
    {
        Session::forget('last_reauth_at');
    }
}