<?php

namespace Tests\Unit;

use App\Models\SecurityConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_config_can_be_created_with_defaults()
    {
        $config = SecurityConfig::create([]);

        $this->assertInstanceOf(SecurityConfig::class, $config);
        $this->assertEquals(5, $config->max_login_attempts);
        $this->assertEquals(30, $config->lockout_duration_minutes);
        $this->assertEquals(12, $config->password_min_length);
        $this->assertTrue($config->password_require_uppercase);
        $this->assertTrue($config->password_require_lowercase);
        $this->assertTrue($config->password_require_numbers);
        $this->assertTrue($config->password_require_special);
        $this->assertEquals(5, $config->password_history_count);
        $this->assertEquals(90, $config->password_expiry_days);
        $this->assertEquals(100000, $config->pbkdf2_iterations);
        $this->assertEquals(120, $config->session_timeout_minutes);
    }

    public function test_security_config_can_be_created_with_custom_values()
    {
        $config = SecurityConfig::create([
            'max_login_attempts' => 3,
            'lockout_duration_minutes' => 60,
            'password_min_length' => 16,
            'password_require_uppercase' => false,
            'password_require_lowercase' => true,
            'password_require_numbers' => true,
            'password_require_special' => false,
            'password_history_count' => 10,
            'password_expiry_days' => 180,
            'pbkdf2_iterations' => 150000,
            'session_timeout_minutes' => 240
        ]);

        $this->assertEquals(3, $config->max_login_attempts);
        $this->assertEquals(60, $config->lockout_duration_minutes);
        $this->assertEquals(16, $config->password_min_length);
        $this->assertFalse($config->password_require_uppercase);
        $this->assertTrue($config->password_require_lowercase);
        $this->assertTrue($config->password_require_numbers);
        $this->assertFalse($config->password_require_special);
        $this->assertEquals(10, $config->password_history_count);
        $this->assertEquals(180, $config->password_expiry_days);
        $this->assertEquals(150000, $config->pbkdf2_iterations);
        $this->assertEquals(240, $config->session_timeout_minutes);
    }

    public function test_get_instance_returns_singleton()
    {
        // First call creates the instance
        $config1 = SecurityConfig::getInstance();
        $this->assertInstanceOf(SecurityConfig::class, $config1);

        // Second call returns the same instance
        $config2 = SecurityConfig::getInstance();
        $this->assertEquals($config1->id, $config2->id);
    }

    public function test_get_instance_creates_config_if_none_exists()
    {
        $this->assertEquals(0, SecurityConfig::count());
        
        $config = SecurityConfig::getInstance();
        
        $this->assertEquals(1, SecurityConfig::count());
        $this->assertInstanceOf(SecurityConfig::class, $config);
    }

    public function test_validation_rules_are_defined()
    {
        $rules = SecurityConfig::validationRules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('max_login_attempts', $rules);
        $this->assertArrayHasKey('lockout_duration_minutes', $rules);
        $this->assertArrayHasKey('password_min_length', $rules);
        $this->assertArrayHasKey('password_require_uppercase', $rules);
        $this->assertArrayHasKey('password_require_lowercase', $rules);
        $this->assertArrayHasKey('password_require_numbers', $rules);
        $this->assertArrayHasKey('password_require_special', $rules);
        $this->assertArrayHasKey('password_history_count', $rules);
        $this->assertArrayHasKey('password_expiry_days', $rules);
        $this->assertArrayHasKey('pbkdf2_iterations', $rules);
        $this->assertArrayHasKey('session_timeout_minutes', $rules);
    }

    public function test_validation_messages_are_defined()
    {
        $messages = SecurityConfig::validationMessages();

        $this->assertIsArray($messages);
        $this->assertArrayHasKey('max_login_attempts.min', $messages);
        $this->assertArrayHasKey('password_min_length.min', $messages);
        $this->assertArrayHasKey('pbkdf2_iterations.min', $messages);
    }

    public function test_boolean_fields_are_cast_correctly()
    {
        $config = SecurityConfig::create([
            'password_require_uppercase' => 1,
            'password_require_lowercase' => 0,
            'password_require_numbers' => '1',
            'password_require_special' => '0'
        ]);

        $this->assertTrue($config->password_require_uppercase);
        $this->assertFalse($config->password_require_lowercase);
        $this->assertTrue($config->password_require_numbers);
        $this->assertFalse($config->password_require_special);
    }

    public function test_integer_fields_are_cast_correctly()
    {
        $config = SecurityConfig::create([
            'max_login_attempts' => '10',
            'password_min_length' => '16',
            'pbkdf2_iterations' => '200000'
        ]);

        $this->assertIsInt($config->max_login_attempts);
        $this->assertIsInt($config->password_min_length);
        $this->assertIsInt($config->pbkdf2_iterations);
        $this->assertEquals(10, $config->max_login_attempts);
        $this->assertEquals(16, $config->password_min_length);
        $this->assertEquals(200000, $config->pbkdf2_iterations);
    }
}