<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Services\PasswordPolicyService;
use App\Services\PBKDF2PasswordHasher;
use App\Services\AuditLogger;
use App\Services\SessionSecurityService;
use App\Http\Controllers\ReauthenticationController;
use Carbon\Carbon;

class PasswordController extends Controller
{
    protected $passwordPolicyService;
    protected $passwordHasher;
    protected $auditLogger;
    protected $sessionSecurityService;

    public function __construct(
        PasswordPolicyService $passwordPolicyService,
        PBKDF2PasswordHasher $passwordHasher,
        AuditLogger $auditLogger,
        SessionSecurityService $sessionSecurityService
    ) {
        $this->middleware('auth');
        $this->middleware('reauth:5'); // Require re-authentication within 5 minutes
        $this->passwordPolicyService = $passwordPolicyService;
        $this->passwordHasher = $passwordHasher;
        $this->auditLogger = $auditLogger;
        $this->sessionSecurityService = $sessionSecurityService;
    }

    /**
     * Affiche le formulaire de changement de mot de passe
     *
     * @return \Illuminate\View\View
     */
    public function showChangeForm()
    {
        return view('auth.change-password');
    }

    /**
     * Traite la demande de changement de mot de passe avec sécurité renforcée
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function change(Request $request)
    {
        $user = Auth::user();

        // Valider les données d'entrée
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|confirmed',
        ]);

        // Vérifier le mot de passe actuel avec le hacheur PBKDF2
        $isCurrentPasswordValid = false;
        if ($user->salt && $user->password_hash) {
            // Utiliser la vérification PBKDF2
            $isCurrentPasswordValid = $this->passwordHasher->verify(
                $request->current_password,
                $user->salt,
                $user->password_hash,
                $user->pbkdf2_iterations ?? 100000
            );
        } else {
            // Repli sur le Hash de Laravel pour les mots de passe hérités
            $isCurrentPasswordValid = Hash::check($request->current_password, $user->password);
        }

        if (!$isCurrentPasswordValid) {
            // Enregistrer la tentative de changement de mot de passe échouée
            $this->auditLogger->logSecurityEvent('password_change_failed', $user->id, [
                'message' => 'Échec du changement de mot de passe - mot de passe actuel incorrect',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ], $request);

            return back()->withErrors(['current_password' => 'Le mot de passe actuel est incorrect.']);
        }

        // Valider le nouveau mot de passe selon la politique
        try {
            $this->passwordPolicyService->validatePassword($request->password, $user->id);
        } catch (\Exception $e) {
            // Enregistrer la violation de la politique de mot de passe
            $this->auditLogger->logSecurityEvent('password_policy_violation', $user->id, [
                'message' => 'Violation de la politique de mot de passe lors du changement',
                'violation' => $e->getMessage(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ], $request);

            return back()->withErrors(['password' => $e->getMessage()]);
        }

        // Hacher le nouveau mot de passe avec PBKDF2
        $hashedData = $this->passwordHasher->hash($request->password);

        // Mettre à jour le mot de passe de l'utilisateur
        $user->update([
            'password_hash' => $hashedData['hash'],
            'salt' => $hashedData['salt'],
            'pbkdf2_iterations' => $hashedData['iterations'],
            'password_changed_at' => Carbon::now(),
            'must_change_password' => false,
        ]);

        // Ajouter à l'historique des mots de passe
        $this->passwordPolicyService->addToHistory($user->id, $hashedData);

        // Enregistrer le changement de mot de passe réussi
        $this->auditLogger->logSecurityEvent('password_changed', $user->id, [
            'message' => 'Mot de passe changé avec succès',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ], $request);

        // Régénérer la session après le changement de mot de passe pour la sécurité
        $this->sessionSecurityService->regenerateSession($request, true);

        // Forcer la ré-authentification pour les futures opérations sensibles
        ReauthenticationController::forceReauth();

        return redirect()->route('dashboard')->with('success', 'Mot de passe mis à jour avec succès.');
    }
}