<?php

namespace Tests\Unit;

use App\Services\PBKDF2PasswordHasher;
use Exception;
use PHPUnit\Framework\TestCase;

class PBKDF2PasswordHasherTest extends TestCase
{
    private PBKDF2PasswordHasher $hasher;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->hasher = new PBKDF2PasswordHasher();
    }
    
    public function test_constructor_sets_default_iterations()
    {
        $hasher = new PBKDF2PasswordHasher();
        $config = $hasher->getConfig();
        
        $this->assertEquals(100000, $config['iterations']);
    }
    
    public function test_constructor_accepts_custom_iterations()
    {
        $hasher = new PBKDF2PasswordHasher(50000);
        $config = $hasher->getConfig();
        
        $this->assertEquals(50000, $config['iterations']);
    }
    
    public function test_hash_returns_expected_structure()
    {
        $password = 'TestPassword123!';
        $result = $this->hasher->hash($password);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('salt', $result);
        $this->assertArrayHasKey('hash', $result);
        $this->assertArrayHasKey('iterations', $result);
        $this->assertArrayHasKey('algorithm', $result);
        
        // Verify base64 encoding
        $this->assertNotFalse(base64_decode($result['salt'], true));
        $this->assertNotFalse(base64_decode($result['hash'], true));
        
        // Verify salt length (32 bytes = 44 characters in base64)
        $decodedSalt = base64_decode($result['salt']);
        $this->assertEquals(32, strlen($decodedSalt));
        
        // Verify hash length (64 bytes = 88 characters in base64)
        $decodedHash = base64_decode($result['hash']);
        $this->assertEquals(64, strlen($decodedHash));
        
        $this->assertEquals(100000, $result['iterations']);
        $this->assertEquals('sha256', $result['algorithm']);
    }
    
    public function test_hash_generates_unique_salts()
    {
        $password = 'TestPassword123!';
        $result1 = $this->hasher->hash($password);
        $result2 = $this->hasher->hash($password);
        
        $this->assertNotEquals($result1['salt'], $result2['salt']);
        $this->assertNotEquals($result1['hash'], $result2['hash']);
    }
    
    public function test_verify_returns_true_for_correct_password()
    {
        $password = 'TestPassword123!';
        $hashData = $this->hasher->hash($password);
        
        $isValid = $this->hasher->verify(
            $password,
            $hashData['salt'],
            $hashData['hash'],
            $hashData['iterations']
        );
        
        $this->assertTrue($isValid, '[FAIL] Password verification should succeed for correct password');
    }
    
    public function test_verify_returns_false_for_incorrect_password()
    {
        $password = 'TestPassword123!';
        $wrongPassword = 'WrongPassword456!';
        $hashData = $this->hasher->hash($password);
        
        $isValid = $this->hasher->verify(
            $wrongPassword,
            $hashData['salt'],
            $hashData['hash'],
            $hashData['iterations']
        );
        
        $this->assertFalse($isValid, '[FAIL] Password verification should fail for incorrect password');
    }
    
    public function test_verify_returns_false_for_invalid_base64_salt()
    {
        $password = 'TestPassword123!';
        $hashData = $this->hasher->hash($password);
        
        $isValid = $this->hasher->verify(
            $password,
            'invalid-base64!@#',
            $hashData['hash'],
            $hashData['iterations']
        );
        
        $this->assertFalse($isValid, '[FAIL] Password verification should fail for invalid base64 salt');
    }
    
    public function test_verify_returns_false_for_invalid_base64_hash()
    {
        $password = 'TestPassword123!';
        $hashData = $this->hasher->hash($password);
        
        $isValid = $this->hasher->verify(
            $password,
            $hashData['salt'],
            'invalid-base64!@#',
            $hashData['iterations']
        );
        
        $this->assertFalse($isValid, '[FAIL] Password verification should fail for invalid base64 hash');
    }
    
    public function test_verify_is_timing_safe()
    {
        $password = 'TestPassword123!';
        $hashData = $this->hasher->hash($password);
        
        // Test with correct password
        $start1 = microtime(true);
        $result1 = $this->hasher->verify(
            $password,
            $hashData['salt'],
            $hashData['hash'],
            $hashData['iterations']
        );
        $time1 = microtime(true) - $start1;
        
        // Test with incorrect password
        $start2 = microtime(true);
        $result2 = $this->hasher->verify(
            'WrongPassword',
            $hashData['salt'],
            $hashData['hash'],
            $hashData['iterations']
        );
        $time2 = microtime(true) - $start2;
        
        $this->assertTrue($result1, '[FAIL] Correct password should verify successfully');
        $this->assertFalse($result2, '[FAIL] Incorrect password should fail verification');
        
        // Times should be similar (within reasonable variance)
        // This is a basic check - in practice, timing attacks are more sophisticated
        $timeDifference = abs($time1 - $time2);
        $averageTime = ($time1 + $time2) / 2;
        $variance = $timeDifference / $averageTime;
        
        // Allow up to 50% variance (generous for unit testing)
        $this->assertLessThan(0.5, $variance, 'Timing difference too large, potential timing attack vulnerability');
    }
    
    public function test_different_iterations_produce_different_hashes()
    {
        $password = 'TestPassword123!';
        
        $hasher1 = new PBKDF2PasswordHasher(10000);
        $hasher2 = new PBKDF2PasswordHasher(20000);
        
        $result1 = $hasher1->hash($password);
        $result2 = $hasher2->hash($password);
        
        $this->assertNotEquals($result1['hash'], $result2['hash']);
        $this->assertEquals(10000, $result1['iterations']);
        $this->assertEquals(20000, $result2['iterations']);
    }
    
    public function test_set_iterations_updates_configuration()
    {
        $this->hasher->setIterations(150000);
        $config = $this->hasher->getConfig();
        
        $this->assertEquals(150000, $config['iterations']);
    }
    
    public function test_set_iterations_throws_exception_for_low_values()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Iterations must be at least 10,000 for security');
        
        $this->hasher->setIterations(5000);
    }
    
    public function test_get_config_returns_all_parameters()
    {
        $config = $this->hasher->getConfig();
        
        $this->assertArrayHasKey('iterations', $config);
        $this->assertArrayHasKey('salt_length', $config);
        $this->assertArrayHasKey('hash_length', $config);
        $this->assertArrayHasKey('algorithm', $config);
        
        $this->assertEquals(100000, $config['iterations']);
        $this->assertEquals(32, $config['salt_length']);
        $this->assertEquals(64, $config['hash_length']);
        $this->assertEquals('sha256', $config['algorithm']);
    }
    
    public function test_hash_handles_empty_password()
    {
        $result = $this->hasher->hash('');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('salt', $result);
        $this->assertArrayHasKey('hash', $result);
        
        // Should still verify correctly
        $isValid = $this->hasher->verify(
            '',
            $result['salt'],
            $result['hash'],
            $result['iterations']
        );
        
        $this->assertTrue($isValid);
    }
    
    public function test_hash_handles_unicode_password()
    {
        $password = 'Tëst🔒Pässwörd123!';
        $result = $this->hasher->hash($password);
        
        $isValid = $this->hasher->verify(
            $password,
            $result['salt'],
            $result['hash'],
            $result['iterations']
        );
        
        $this->assertTrue($isValid);
    }
    
    public function test_hash_produces_consistent_length_output()
    {
        $passwords = [
            'short',
            'medium_length_password',
            'very_long_password_with_many_characters_and_symbols_!@#$%^&*()_+{}[]|\\:";\'<>?,./',
            ''
        ];
        
        foreach ($passwords as $password) {
            $result = $this->hasher->hash($password);
            
            $decodedSalt = base64_decode($result['salt']);
            $decodedHash = base64_decode($result['hash']);
            
            $this->assertEquals(32, strlen($decodedSalt), "Salt length inconsistent for password: $password");
            $this->assertEquals(64, strlen($decodedHash), "Hash length inconsistent for password: $password");
        }
    }
}