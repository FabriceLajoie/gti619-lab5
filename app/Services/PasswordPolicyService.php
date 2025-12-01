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
        
        // Check minimum length
        if (strlen($password) < $requirements['min_length']) {
            $errors[] = "Password must be at least {$requirements['min_length']} characters long";
        }
        
        // Check maximum length (security best practice)
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
     * Check if user's password has expired
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
        
        // If user has no password_changed_at date, consider it expired
        if (!$user->password_changed_at) {
            return true;
        }
        
        $expiryDate = $user->password_changed_at->addDays($expiryDays);
        return Carbon::now()->isAfter($expiryDate);
    }
    
    /**
     * Check if user must change password (forced change)
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
     * Mark user password as changed (updates password_changed_at and clears must_change_password)
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
     * Get password policy requirements as human-readable text
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
        
        // Check for keyboard patterns (only longer patterns to avoid false positives)
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
        
        // Check for simple sequences (4+ characters to avoid false positives)
        if (preg_match('/(?:0123|1234|2345|3456|4567|5678|6789|7890|abcd|bcde|cdef|defg|efgh|fghi|ghij|hijk|ijkl|jklm|klmn|lmno|mnop|nopq|opqr|pqrs|qrst|rstu|stuv|tuvw|uvwx|vwxy|wxyz)/i', $password)) {
            $errors[] = "Password cannot contain simple sequences (1234, abcd, etc.)";
        }
        
        return $errors;
    }
    
    /**
     * Generate password strength score (0-100)
     * 
     * @param string $password The password to score
     * @return array Score and feedback
     */
    public function calculatePasswordStrength(string $password): array
    {
        $score = 0;
        $feedback = [];
        
        // Length scoring (up to 25 points)
        $length = strlen($password);
        if ($length >= 8) $score += 5;
        if ($length >= 12) $score += 5;
        if ($length >= 16) $score += 10;
        if ($length >= 20) $score += 5;
        
        // Character variety scoring (up to 40 points)
        if (preg_match('/[a-z]/', $password)) {
            $score += 10;
        } else {
            $feedback[] = "Add lowercase letters";
        }
        
        if (preg_match('/[A-Z]/', $password)) {
            $score += 10;
        } else {
            $feedback[] = "Add uppercase letters";
        }
        
        if (preg_match('/[0-9]/', $password)) {
            $score += 10;
        } else {
            $feedback[] = "Add numbers";
        }
        
        if (preg_match('/[^A-Za-z0-9]/', $password)) {
            $score += 10;
        } else {
            $feedback[] = "Add special characters";
        }
        
        // Complexity scoring (up to 35 points)
        $uniqueChars = count(array_unique(str_split($password)));
        $score += min(15, $uniqueChars);
        
        // Penalty for patterns
        if (preg_match('/(.)\1{2,}/', $password)) {
            $score -= 10;
            $feedback[] = "Avoid repeated characters";
        }
        
        // Bonus for mixed case within words
        if (preg_match('/[a-z][A-Z]|[A-Z][a-z]/', $password)) {
            $score += 5;
        }
        
        // Bonus for numbers mixed with letters
        if (preg_match('/[a-zA-Z][0-9]|[0-9][a-zA-Z]/', $password)) {
            $score += 5;
        }
        
        // Bonus for special chars mixed with alphanumeric
        if (preg_match('/[a-zA-Z0-9][^a-zA-Z0-9]|[^a-zA-Z0-9][a-zA-Z0-9]/', $password)) {
            $score += 5;
        }
        
        $score = max(0, min(100, $score));
        
        // Determine strength level
        if ($score < 30) {
            $strength = 'Very Weak';
        } elseif ($score < 50) {
            $strength = 'Weak';
        } elseif ($score < 70) {
            $strength = 'Fair';
        } elseif ($score < 85) {
            $strength = 'Good';
        } else {
            $strength = 'Strong';
        }
        
        return [
            'score' => $score,
            'strength' => $strength,
            'feedback' => $feedback
        ];
    }
}