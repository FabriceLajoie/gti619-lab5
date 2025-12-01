<?php

namespace App\Services;

use App\Models\User;
use App\Services\SecurityConfigService;
use App\Services\PasswordHistoryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Password Policy Service
 * 
 * Manages password complexity validation, history checking, and expiration logic
 * Integrates with SecurityConfigService for configurable password requirements
 */
class PasswordPolicyService
{
    /**
     * Security configuration service
     */
    private SecurityConfigService $securityConfig;
    
    /**
     * Password history service
     */
    private PasswordHistoryService $passwordHistory;
    
    /**
     * Constructor
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
     * Validate password against all policy requirements
     * 
     * @param string $password The password to validate
     * @param User|null $user The user (for history checking)
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    public function validatePassword(string $password, User $user = null): array
    {
        $errors = [];
        
        // Validate complexity requirements
        $complexityResult = $this->validateComplexity($password);
        if (!$complexityResult['valid']) {
            $errors = array_merge($errors, $complexityResult['errors']);
        }
        
        // Validate against password history if user provided
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
     * Validate password complexity requirements
     * 
     * @param string $password The password to validate
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    public function validateComplexity(string $password): array
    {
        $requirements = $this->securityConfig->getPasswordRequirements();
        $errors = [];
        
        // Check min length
        if (strlen($password) < $requirements['min_length']) {
            $errors[] = "Password must be at least {$requirements['min_length']} characters long";
        }
        
        // Check max length
        if (strlen($password) > 128) {
            $errors[] = "Password cannot exceed 128 characters";
        }
        
        // Check uppercase requirement
        if ($requirements['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter (A-Z)";
        }
        
        // Check lowercase requirement
        if ($requirements['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter (a-z)";
        }
        
        // Check numbers requirement
        if ($requirements['require_numbers'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number (0-9)";
        }
        
        // Check special characters requirement
        if ($requirements['require_special'] && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character (!@#$%^&*()_+-=[]{}|;:,.<>?)";
        }
        
        // Check for common weak patterns
        $weaknessErrors = $this->checkWeakPatterns($password);
        $errors = array_merge($errors, $weaknessErrors);
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate password against user's password history
     * 
     * @param string $password The password to validate
     * @param User $user The user
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    public function validatePasswordHistory(string $password, User $user): array
    {
        $historyCount = $this->securityConfig->getPasswordHistoryCount();
        
        // Skip history check if history count is 0
        if ($historyCount === 0) {
            return ['valid' => true, 'errors' => []];
        }
        
        $isReused = $this->passwordHistory->isPasswordReused($user, $password, $historyCount);
        
        if ($isReused) {
            return [
                'valid' => false,
                'errors' => ["Password cannot be the same as any of your last {$historyCount} passwords"]
            ];
        }
        
        return ['valid' => true, 'errors' => []];
    }
    
    /**
     * Check if user's password expired
     * 
     * @param User $user The user
     * @return bool True if password has expired, false otherwise
     */
    public function isPasswordExpired(User $user): bool
    {
        $expiryDays = $this->securityConfig->getPasswordExpiryDays();
        
        // If expiry is disabled (0 days), password never expires
        if ($expiryDays === 0) {
            return false;
        }
        
        // If user has no password_changed_at date, consider expired
        if (!$user->password_changed_at) {
            return true;
        }
        
        $expiryDate = $user->password_changed_at->addDays($expiryDays);
        return Carbon::now()->isAfter($expiryDate);
    }
    
    /**
     * Check if user must change password
     * 
     * @param User $user The user
     * @return bool True if user must change password, false otherwise
     */
    public function mustChangePassword(User $user): bool
    {
        return $user->must_change_password || $this->isPasswordExpired($user);
    }
    
    /**
     * Get days until password expires
     * 
     * @param User $user The user
     * @return int|null Days until expiry, null if no expiry or already expired
     */
    public function getDaysUntilExpiry(User $user): ?int
    {
        $expiryDays = $this->securityConfig->getPasswordExpiryDays();
        
        // If expiry is disabled, return null
        if ($expiryDays === 0 || !$user->password_changed_at) {
            return null;
        }
        
        $expiryDate = $user->password_changed_at->copy()->addDays($expiryDays);
        $daysUntilExpiry = Carbon::now()->diffInDays($expiryDate, false);
        
        // Return null if already expired
        return $daysUntilExpiry > 0 ? $daysUntilExpiry : null;
    }
    
    /**
     * Mark user password as changed (updates password_changed_at et clears must_change_password)
     * 
     * @param User $user The user
     * @return void
     */
    public function markPasswordChanged(User $user): void
    {
        $user->update([
            'password_changed_at' => Carbon::now(),
            'must_change_password' => false
        ]);
        
        Log::info('Password changed for user', [
            'user_id' => $user->id,
            'username' => $user->name,
            'changed_at' => Carbon::now()->toISOString()
        ]);
    }
    
    /**
     * Force user to change password on next login
     * 
     * @param User $user The user
     * @return void
     */
    public function forcePasswordChange(User $user): void
    {
        $user->update(['must_change_password' => true]);
        
        Log::info('Password change forced for user', [
            'user_id' => $user->id,
            'username' => $user->name,
            'forced_at' => Carbon::now()->toISOString()
        ]);
    }
    
    /**
     * Get password policy requirements as text
     * 
     * @return array Array of requirement descriptions
     */
    public function getPasswordRequirementsText(): array
    {
        $requirements = $this->securityConfig->getPasswordRequirements();
        $text = [];
        
        $text[] = "Must be at least {$requirements['min_length']} characters long";
        
        if ($requirements['require_uppercase']) {
            $text[] = "Must contain at least one uppercase letter (A-Z)";
        }
        
        if ($requirements['require_lowercase']) {
            $text[] = "Must contain at least one lowercase letter (a-z)";
        }
        
        if ($requirements['require_numbers']) {
            $text[] = "Must contain at least one number (0-9)";
        }
        
        if ($requirements['require_special']) {
            $text[] = "Must contain at least one special character (!@#$%^&*()_+-=[]{}|;:,.<>?)";
        }
        
        $historyCount = $this->securityConfig->getPasswordHistoryCount();
        if ($historyCount > 0) {
            $text[] = "Cannot be the same as any of your last {$historyCount} passwords";
        }
        
        $expiryDays = $this->securityConfig->getPasswordExpiryDays();
        if ($expiryDays > 0) {
            $text[] = "Must be changed every {$expiryDays} days";
        }
        
        return $text;
    }
    
    /**
     * Check for common weak password patterns
     * 
     * @param string $password The password to check
     * @return array Array of weakness error messages
     */
    private function checkWeakPatterns(string $password): array
    {
        $errors = [];
        
        // Check for common weak passwords
        $commonPasswords = [
            'password', 'password123', '123456', '123456789', 'qwerty',
            'abc123', 'password1', 'admin', 'administrator', 'root',
            'user', 'guest', 'test', 'demo', 'welcome'
        ];
        
        if (in_array(strtolower($password), $commonPasswords)) {
            $errors[] = "Password is too common and easily guessable";
        }
        
        // Check for keyboard patterns
        $keyboardPatterns = [
            'qwerty', 'asdf', 'zxcv', '12345', 'abcde', '!@#$%'
        ];
        
        foreach ($keyboardPatterns as $pattern) {
            if (stripos($password, $pattern) !== false) {
                $errors[] = "Password contains keyboard patterns that are easily guessable";
                break;
            }
        }
        
        // Check for repeated characters (more than 3 in a row)
        if (preg_match('/(.)\1{3,}/', $password)) {
            $errors[] = "Password cannot contain more than 3 repeated characters in a row";
        }
        
        // Check for simple sequences
        if (preg_match('/(?:0123|1234|2345|3456|4567|5678|6789|7890|abcd|bcde|cdef|defg|efgh|fghi|ghij|hijk|ijkl|jklm|klmn|lmno|mnop|nopq|opqr|pqrs|qrst|rstu|stuv|tuvw|uvwx|vwxy|wxyz)/i', $password)) {
            $errors[] = "Password cannot contain simple sequences (1234, abcd, etc.)";
        }
        
        return $errors;
    }
    
}