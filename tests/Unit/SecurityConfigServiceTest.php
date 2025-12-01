<?php

namespace Tests\Unit;

use App\Models\SecurityConfig;
use App\Services\SecurityConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SecurityConfigServiceTest extends TestCase
{
    use RefreshDatabase;

    private SecurityConfigService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SecurityConfigService();
    }

    public function test_get_config_returns_security_config_instance()
    {
        $config = $this->service->getConfig();
        
        $this->assertInstanceOf(SecurityConfig::class, $config);
    }

    public function test_get_returns_specific_configuration_value()
    {
        $config = SecurityConfig::getInstance();
        $config->update(['max_login_attempts' => 7]);
        
        $value = $this->service->get('max_login_attempts');
        
        $this->assertEquals(7, $value);
    }

    public function test_get_returns_default_when_key_not_found()
    {
        $value = $this->service->get('nonexistent_key', 'default_value');
        
        $this->assertEquals('default_value', $value);
    }

    public function test_update_config_validates_input()
    {
        $this->expectException(ValidationException::class);
        
        $this->service->updateConfig([
            'max_login_attempts' => -1, // Invalid: must be positive
            'password_min_length' => 5   // Invalid: must be at least 8
        ]);
    }

    public function test_update_config_updates_successfully_with_valid_data()
    {
        $data = [
            'max_login_attempts' => 10,
            'lockout_duration_minutes' => 45,
            'password_min_length' => 16
        ];
        
        $config = $this->service->updateConfig($data);
        
        $this->assertEquals(10, $config->max_login_attempts);
        $this->assertEquals(45, $config->lockout_duration_minutes);
        $this->assertEquals(16, $config->password_min_length);
    }

    public function test_update_config_enforces_business_rules()
    {
        $this->expectException(ValidationException::class);
        
        try {
            $this->service->updateConfig([
                'password_require_uppercase' => false,
                'password_require_lowercase' => false,
                'password_require_numbers' => false,
                'password_require_special' => false
            ]);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('password_requirements', $e->errors());
            $this->assertContains('At least one password requirement must be enabled', $e->errors()['password_requirements']);
            throw $e;
        }
    }

    public function test_update_config_validates_pbkdf2_iterations_minimum()
    {
        $this->expectException(ValidationException::class);
        
        try {
            $this->service->updateConfig([
                'pbkdf2_iterations' => 25000
            ]);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('pbkdf2_iterations', $e->errors());
            $this->assertContains('PBKDF2 iterations should be at least 50,000 for adequate security', $e->errors()['pbkdf2_iterations']);
            throw $e;
        }
    }

    public function test_get_max_login_attempts_returns_correct_value()
    {
        $config = SecurityConfig::getInstance();
        $config->update(['max_login_attempts' => 8]);
        
        $attempts = $this->service->getMaxLoginAttempts();
        
        $this->assertEquals(8, $attempts);
    }

    public function test_get_lockout_duration_minutes_returns_correct_value()
    {
        $config = SecurityConfig::getInstance();
        $config->update(['lockout_duration_minutes' => 60]);
        
        $duration = $this->service->getLockoutDurationMinutes();
        
        $this->assertEquals(60, $duration);
    }

    public function test_get_password_min_length_returns_correct_value()
    {
        $config = SecurityConfig::getInstance();
        $config->update(['password_min_length' => 14]);
        
        $length = $this->service->getPasswordMinLength();
        
        $this->assertEquals(14, $length);
    }

    public function test_get_password_requirements_returns_array()
    {
        $config = SecurityConfig::getInstance();
        $config->update([
            'password_min_length' => 16,
            'password_require_uppercase' => true,
            'password_require_lowercase' => false,
            'password_require_numbers' => true,
            'password_require_special' => false
        ]);
        
        $requirements = $this->service->getPasswordRequirements();
        
        $this->assertIsArray($requirements);
        $this->assertEquals(16, $requirements['min_length']);
        $this->assertTrue($requirements['require_uppercase']);
        $this->assertFalse($requirements['require_lowercase']);
        $this->assertTrue($requirements['require_numbers']);
        $this->assertFalse($requirements['require_special']);
    }

    public function test_get_password_history_count_returns_correct_value()
    {
        $config = SecurityConfig::getInstance();
        $config->update(['password_history_count' => 8]);
        
        $count = $this->service->getPasswordHistoryCount();
        
        $this->assertEquals(8, $count);
    }

    public function test_get_password_expiry_days_returns_correct_value()
    {
        $config = SecurityConfig::getInstance();
        $config->update(['password_expiry_days' => 120]);
        
        $days = $this->service->getPasswordExpiryDays();
        
        $this->assertEquals(120, $days);
    }

    public function test_get_pbkdf2_iterations_returns_correct_value()
    {
        $config = SecurityConfig::getInstance();
        $config->update(['pbkdf2_iterations' => 150000]);
        
        $iterations = $this->service->getPbkdf2Iterations();
        
        $this->assertEquals(150000, $iterations);
    }

    public function test_get_session_timeout_minutes_returns_correct_value()
    {
        $config = SecurityConfig::getInstance();
        $config->update(['session_timeout_minutes' => 180]);
        
        $timeout = $this->service->getSessionTimeoutMinutes();
        
        $this->assertEquals(180, $timeout);
    }

    public function test_validate_password_complexity_with_valid_password()
    {
        $config = SecurityConfig::getInstance();
        $config->update([
            'password_min_length' => 8,
            'password_require_uppercase' => true,
            'password_require_lowercase' => true,
            'password_require_numbers' => true,
            'password_require_special' => true
        ]);
        
        $result = $this->service->validatePasswordComplexity('MyP@ssw0rd123');
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validate_password_complexity_with_invalid_password()
    {
        $config = SecurityConfig::getInstance();
        $config->update([
            'password_min_length' => 12,
            'password_require_uppercase' => true,
            'password_require_lowercase' => true,
            'password_require_numbers' => true,
            'password_require_special' => true
        ]);
        
        $result = $this->service->validatePasswordComplexity('password');
        
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertContains('Password must be at least 12 characters long', $result['errors']);
        $this->assertContains('Password must contain at least one uppercase letter', $result['errors']);
        $this->assertContains('Password must contain at least one number', $result['errors']);
        $this->assertContains('Password must contain at least one special character', $result['errors']);
    }

    public function test_validate_password_complexity_respects_disabled_requirements()
    {
        $config = SecurityConfig::getInstance();
        $config->update([
            'password_min_length' => 8,
            'password_require_uppercase' => false,
            'password_require_lowercase' => true,
            'password_require_numbers' => false,
            'password_require_special' => false
        ]);
        
        $result = $this->service->validatePasswordComplexity('password');
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_reset_to_defaults_restores_default_configuration()
    {
        // First, change some values
        $this->service->updateConfig([
            'max_login_attempts' => 10,
            'password_min_length' => 20,
            'pbkdf2_iterations' => 200000
        ]);
        
        // Reset to defaults
        $config = $this->service->resetToDefaults();
        
        $this->assertEquals(5, $config->max_login_attempts);
        $this->assertEquals(12, $config->password_min_length);
        $this->assertEquals(100000, $config->pbkdf2_iterations);
    }
}