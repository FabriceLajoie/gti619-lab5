<?php

namespace App\Services;

use App\Models\SecurityConfig;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SecurityConfigService
{
    private $config;

    public function __construct()
    {
        //
        }

    /**
     * Get the current security configuration
     */
    public function getConfig()
    {
        return SecurityConfig::getInstance();
    }

    /**
     * Update security configuration with validation
     */
    public function updateConfig(array $data)
    {
        $validator = Validator::make($data, SecurityConfig::validationRules(), SecurityConfig::validationMessages());

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Additional business logic validation
        $this->validateBusinessRules($data);

        $config = SecurityConfig::getInstance();
        $config->update($data);
        
        return $config->fresh();
    }

    /**
     * Get specific configuration value
     */
    public function get($key, $default = null)
    {
        return $this->getConfig()->getAttribute($key) ?? $default;
    }

    /**
     * Get maximum login attempts
     */
    public function getMaxLoginAttempts()
    {
        return $this->get('max_login_attempts', 5);
    }

    /**
     * Get lockout duration in minutes
     */
    public function getLockoutDurationMinutes()
    {
        return $this->get('lockout_duration_minutes', 30);
    }

    /**
     * Get password minimum length
     */
    public function getPasswordMinLength()
    {
        return $this->get('password_min_length', 12);
    }

    /**
     * Get password requirements as array
     */
    public function getPasswordRequirements()
    {
        return [
            'min_length' => $this->get('password_min_length', 12),
            'require_uppercase' => $this->get('password_require_uppercase', true),
            'require_lowercase' => $this->get('password_require_lowercase', true),
            'require_numbers' => $this->get('password_require_numbers', true),
            'require_special' => $this->get('password_require_special', true),
        ];
    }

    /**
     * Get password history count
     */
    public function getPasswordHistoryCount()
    {
        return $this->get('password_history_count', 5);
    }

    /**
     * Get password expiry days
     */
    public function getPasswordExpiryDays()
    {
        return $this->get('password_expiry_days', 90);
    }

    /**
     * Get PBKDF2 iterations
     */
    public function getPbkdf2Iterations()
    {
        return $this->get('pbkdf2_iterations', 100000);
    }

    /**
     * Get session timeout in minutes
     */
    public function getSessionTimeoutMinutes()
    {
        return $this->get('session_timeout_minutes', 120);
    }

    /**
     * Check if password complexity requirements are met
     */
    public function validatePasswordComplexity($password)
    {
        $requirements = $this->getPasswordRequirements();
        $errors = [];

        // Check minimum length
        if (strlen($password) < $requirements['min_length']) {
            $errors[] = "Password must be at least {$requirements['min_length']} characters long";
        }

        // Check uppercase requirement
        if ($requirements['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }

        // Check lowercase requirement
        if ($requirements['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }

        // Check numbers requirement
        if ($requirements['require_numbers'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }

        // Check special characters requirement
        if ($requirements['require_special'] && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Reset configuration to defaults
     */
    public function resetToDefaults()
    {
        $defaults = [
            'max_login_attempts' => 5,
            'lockout_duration_minutes' => 30,
            'password_min_length' => 12,
            'password_require_uppercase' => true,
            'password_require_lowercase' => true,
            'password_require_numbers' => true,
            'password_require_special' => true,
            'password_history_count' => 5,
            'password_expiry_days' => 90,
            'pbkdf2_iterations' => 100000,
            'session_timeout_minutes' => 120
        ];

        return $this->updateConfig($defaults);
    }

    /**
     * Additional business logic validation
     */
    private function validateBusinessRules(array $data)
    {
        // Ensure at least one password requirement is enabled
        $passwordRequirements = [
            $data['password_require_uppercase'] ?? $this->get('password_require_uppercase'),
            $data['password_require_lowercase'] ?? $this->get('password_require_lowercase'),
            $data['password_require_numbers'] ?? $this->get('password_require_numbers'),
            $data['password_require_special'] ?? $this->get('password_require_special')
        ];

        if (!in_array(true, $passwordRequirements)) {
            throw ValidationException::withMessages([
                'password_requirements' => 'At least one password requirement must be enabled'
            ]);
        }

        // Validate PBKDF2 iterations for security vs performance balance
        $iterations = $data['pbkdf2_iterations'] ?? $this->get('pbkdf2_iterations');
        if ($iterations < 50000) {
            throw ValidationException::withMessages([
                'pbkdf2_iterations' => 'PBKDF2 iterations should be at least 50,000 for adequate security'
            ]);
        }
    }
}