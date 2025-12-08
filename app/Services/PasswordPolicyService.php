<?php

namespace App\Services;

use App\Models\User;
use App\Services\SecurityConfigService;
use App\Services\PasswordHistoryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Service de Politique de Mot de Passe
 * 
 * Gère la validation de complexité des mots de passe, la vérification de l'historique et la logique d'expiration
 * S'intègre avec SecurityConfigService pour des exigences de mot de passe configurables
 */
class PasswordPolicyService
{
    /**
     * Service de configuration de sécurité
     */
    private SecurityConfigService $securityConfig;
    
    /**
     * Service d'historique des mots de passe
     */
    private PasswordHistoryService $passwordHistory;
    
    /**
     * Constructeur
     * 
     * @param SecurityConfigService $securityConfig
     * @param PasswordHistoryService $passwordHistory
     */
    public function __construct(
        SecurityConfigService $securityConfig,
        PasswordHistoryService $passwordHistory
    ) {
        $this->securityConfig = $securityConfig;
        $this->passwordHistory = $passwordHistory;
    }
    
    /**
     * Valider le mot de passe contre toutes les exigences de politique
     * 
     * @param string $password Le mot de passe à valider
     * @param User|null $user L'utilisateur (pour la vérification de l'historique)
     * @return array Résultat de validation avec un booléen 'valid' et un tableau 'errors'
     */
    public function validatePassword(string $password, User $user = null): array
    {
        $errors = [];
        
        // Valider les exigences de complexité
        $complexityResult = $this->validateComplexity($password);
        if (!$complexityResult['valid']) {
            $errors = array_merge($errors, $complexityResult['errors']);
        }
        
        // Valider contre l'historique des mots de passe si l'utilisateur est fourni
        if ($user !== null) {
            $historyResult = $this->validatePasswordHistory($password, $user);
            if (!$historyResult['valid']) {
                $errors = array_merge($errors, $historyResult['errors']);
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Valider les exigences de complexité du mot de passe
     * 
     * @param string $password Le mot de passe à valider
     * @return array Résultat de validation avec un booléen 'valid' et un tableau 'errors'
     */
    public function validateComplexity(string $password): array
    {
        $requirements = $this->securityConfig->getPasswordRequirements();
        $errors = [];
        
        // Vérifier la longueur minimale
        if (strlen($password) < $requirements['min_length']) {
            $errors[] = "Le mot de passe doit contenir au moins {$requirements['min_length']} caractères";
        }
        
        // Vérifier la longueur maximale
        if (strlen($password) > 128) {
            $errors[] = "Le mot de passe ne peut pas dépasser 128 caractères";
        }
        
        // Vérifier l'exigence de majuscules
        if ($requirements['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins une lettre majuscule (A-Z)";
        }
        
        // Vérifier l'exigence de minuscules
        if ($requirements['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins une lettre minuscule (a-z)";
        }
        
        // Vérifier l'exigence de chiffres
        if ($requirements['require_numbers'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins un chiffre (0-9)";
        }
        
        // Vérifier l'exigence de caractères spéciaux
        if ($requirements['require_special'] && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins un caractère spécial (!@#$%^&*()_+-=[]{}|;:,.<>?)";
        }
        
        // Vérifier les motifs faibles courants
        $weaknessErrors = $this->checkWeakPatterns($password);
        $errors = array_merge($errors, $weaknessErrors);
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Valider le mot de passe contre l'historique des mots de passe de l'utilisateur
     * 
     * @param string $password Le mot de passe à valider
     * @param User $user L'utilisateur
     * @return array Résultat de validation avec un booléen 'valid' et un tableau 'errors'
     */
    public function validatePasswordHistory(string $password, User $user): array
    {
        $historyCount = $this->securityConfig->getPasswordHistoryCount();
        
        // Ignorer la vérification de l'historique si le nombre d'historique est 0
        if ($historyCount === 0) {
            return ['valid' => true, 'errors' => []];
        }
        
        $isReused = $this->passwordHistory->isPasswordReused($user, $password, $historyCount);
        
        if ($isReused) {
            return [
                'valid' => false,
                'errors' => ["Le mot de passe ne peut pas être identique à l'un de vos {$historyCount} derniers mots de passe"]
            ];
        }
        
        return ['valid' => true, 'errors' => []];
    }
    
    /**
     * Vérifier si le mot de passe de l'utilisateur a expiré
     * 
     * @param User $user L'utilisateur
     * @return bool Vrai si le mot de passe a expiré, faux sinon
     */
    public function isPasswordExpired(User $user): bool
    {
        $expiryDays = $this->securityConfig->getPasswordExpiryDays();
        
        // Si l'expiration est désactivée (0 jours), le mot de passe n'expire jamais
        if ($expiryDays === 0) {
            return false;
        }
        
        // Si l'utilisateur n'a pas de date password_changed_at, considérer comme expiré
        if (!$user->password_changed_at) {
            return true;
        }
        
        $expiryDate = $user->password_changed_at->addDays($expiryDays);
        return Carbon::now()->isAfter($expiryDate);
    }
    
    /**
     * Vérifier si l'utilisateur doit changer son mot de passe
     * 
     * @param User $user L'utilisateur
     * @return bool Vrai si l'utilisateur doit changer son mot de passe, faux sinon
     */
    public function mustChangePassword(User $user): bool
    {
        return $user->must_change_password || $this->isPasswordExpired($user);
    }
    
    /**
     * Obtenir le nombre de jours jusqu'à l'expiration du mot de passe
     * 
     * @param User $user L'utilisateur
     * @return int|null Jours jusqu'à l'expiration, null si pas d'expiration ou déjà expiré
     */
    public function getDaysUntilExpiry(User $user): ?int
    {
        $expiryDays = $this->securityConfig->getPasswordExpiryDays();
        
        // Si l'expiration est désactivée, retourner null
        if ($expiryDays === 0 || !$user->password_changed_at) {
            return null;
        }
        
        $expiryDate = $user->password_changed_at->copy()->addDays($expiryDays);
        $daysUntilExpiry = Carbon::now()->diffInDays($expiryDate, false);
        
        // Retourner null si déjà expiré
        return $daysUntilExpiry > 0 ? $daysUntilExpiry : null;
    }
    
    /**
     * Marquer le mot de passe utilisateur comme changé (met à jour password_changed_at et efface must_change_password)
     * 
     * @param User $user L'utilisateur
     * @return void
     */
    public function markPasswordChanged(User $user): void
    {
        $user->update([
            'password_changed_at' => Carbon::now(),
            'must_change_password' => false
        ]);
        
        Log::info('Mot de passe changé pour l\'utilisateur', [
            'user_id' => $user->id,
            'username' => $user->name,
            'changed_at' => Carbon::now()->toISOString()
        ]);
    }
    
    /**
     * Forcer l'utilisateur à changer son mot de passe à la prochaine connexion
     * 
     * @param User $user L'utilisateur
     * @return void
     */
    public function forcePasswordChange(User $user): void
    {
        $user->update(['must_change_password' => true]);
        
        Log::info('Changement de mot de passe forcé pour l\'utilisateur', [
            'user_id' => $user->id,
            'username' => $user->name,
            'forced_at' => Carbon::now()->toISOString()
        ]);
    }
    
    /**
     * Obtenir les exigences de politique de mot de passe sous forme de texte
     * 
     * @return array Tableau des descriptions d'exigences
     */
    public function getPasswordRequirementsText(): array
    {
        $requirements = $this->securityConfig->getPasswordRequirements();
        $text = [];
        
        $text[] = "Doit contenir au moins {$requirements['min_length']} caractères";
        
        if ($requirements['require_uppercase']) {
            $text[] = "Doit contenir au moins une lettre majuscule (A-Z)";
        }
        
        if ($requirements['require_lowercase']) {
            $text[] = "Doit contenir au moins une lettre minuscule (a-z)";
        }
        
        if ($requirements['require_numbers']) {
            $text[] = "Doit contenir au moins un chiffre (0-9)";
        }
        
        if ($requirements['require_special']) {
            $text[] = "Doit contenir au moins un caractère spécial (!@#$%^&*()_+-=[]{}|;:,.<>?)";
        }
        
        $historyCount = $this->securityConfig->getPasswordHistoryCount();
        if ($historyCount > 0) {
            $text[] = "Ne peut pas être identique à l'un de vos {$historyCount} derniers mots de passe";
        }
        
        $expiryDays = $this->securityConfig->getPasswordExpiryDays();
        if ($expiryDays > 0) {
            $text[] = "Doit être changé tous les {$expiryDays} jours";
        }
        
        return $text;
    }
    
    /**
     * Vérifier les motifs de mots de passe faibles courants
     * 
     * @param string $password Le mot de passe à vérifier
     * @return array Tableau des messages d'erreur de faiblesse
     */
    private function checkWeakPatterns(string $password): array
    {
        $errors = [];
        
        // Vérifier les mots de passe faibles courants
        $commonPasswords = [
            'password', 'password123', '123456', '123456789', 'qwerty',
            'abc123', 'password1', 'admin', 'administrator', 'root',
            'user', 'guest', 'test', 'demo', 'welcome'
        ];
        
        if (in_array(strtolower($password), $commonPasswords)) {
            $errors[] = "Le mot de passe est trop courant et facilement devinable";
        }
        
        // Vérifier les motifs de clavier
        $keyboardPatterns = [
            'qwerty', 'asdf', 'zxcv', '12345', 'abcde', '!@#$%'
        ];
        
        foreach ($keyboardPatterns as $pattern) {
            if (stripos($password, $pattern) !== false) {
                $errors[] = "Le mot de passe contient des motifs de clavier facilement devinables";
                break;
            }
        }
        
        // Vérifier les caractères répétés 3 times repeating
        if (preg_match('/(.)\1{3,}/', $password)) {
            $errors[] = "Le mot de passe ne peut pas contenir plus de 3 caractères répétés d'affilée";
        }
        
        // Vérifier les séquences trop simple
        if (preg_match('/(?:0123|1234|2345|3456|4567|5678|6789|7890|abcd|bcde|cdef|defg|efgh|fghi|ghij|hijk|ijkl|jklm|klmn|lmno|mnop|nopq|opqr|pqrs|qrst|rstu|stuv|tuvw|uvwx|vwxy|wxyz)/i', $password)) {
            $errors[] = "Le mot de passe ne peut pas contenir de séquences simples (1234, abcd, etc.)";
        }
        
        return $errors;
    }
    
}