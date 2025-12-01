<?php

namespace Tests\Unit;

use App\Services\PBKDF2PasswordHasher;
use App\Services\PasswordHistoryService;
use PHPUnit\Framework\TestCase;

class PBKDF2IntegrationTest extends TestCase
{
    private PBKDF2PasswordHasher $hasher;
    private PasswordHistoryService $historyService;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->hasher = new PBKDF2PasswordHasher();
        $this->historyService = new PasswordHistoryService($this->hasher);
    }
    
    public function test_pbkdf2_hasher_and_history_service_integration()
    {
        // Test that the services can work together without database
        $password1 = 'TestPassword123!';
        $password2 = 'DifferentPassword456!';
        
        // Hash two different passwords
        $hash1 = $this->hasher->hash($password1);
        $hash2 = $this->hasher->hash($password2);
        
        // Verify they produce different results
        $this->assertNotEquals($hash1['salt'], $hash2['salt']);
        $this->assertNotEquals($hash1['hash'], $hash2['hash']);
        
        // Verify each password verifies correctly
        $this->assertTrue($this->hasher->verify(
            $password1,
            $hash1['salt'],
            $hash1['hash'],
            $hash1['iterations']
        ), '[FAIL] Password1 should verify correctly with its own hash');
        
        $this->assertTrue($this->hasher->verify(
            $password2,
            $hash2['salt'],
            $hash2['hash'],
            $hash2['iterations']
        ), '[FAIL] Password2 should verify correctly with its own hash');
        
        // Verify cross-verification fails
        $this->assertFalse($this->hasher->verify(
            $password1,
            $hash2['salt'],
            $hash2['hash'],
            $hash2['iterations']
        ), '[FAIL] Password1 should not verify with Password2 hash');
        
        $this->assertFalse($this->hasher->verify(
            $password2,
            $hash1['salt'],
            $hash1['hash'],
            $hash1['iterations']
        ), '[FAIL] Password2 should not verify with Password1 hash');
    }
    
    public function test_pbkdf2_configuration_methods()
    {
        // Test configuration methods
        $config = $this->hasher->getConfig();
        
        $this->assertArrayHasKey('iterations', $config);
        $this->assertArrayHasKey('salt_length', $config);
        $this->assertArrayHasKey('hash_length', $config);
        $this->assertArrayHasKey('algorithm', $config);
        
        // Test setting iterations
        $this->hasher->setIterations(150000);
        $newConfig = $this->hasher->getConfig();
        $this->assertEquals(150000, $newConfig['iterations']);
        
        // Test that new iteration count affects hashing
        $password = 'TestPassword123!';
        $hash = $this->hasher->hash($password);
        $this->assertEquals(150000, $hash['iterations']);
        
        // Verify password still works with new iteration count
        $this->assertTrue($this->hasher->verify(
            $password,
            $hash['salt'],
            $hash['hash'],
            $hash['iterations']
        ));
    }
    
    public function test_password_history_service_configuration()
    {
        // Test history service configuration methods
        $this->assertEquals(5, $this->historyService->getDefaultHistoryCount());
        
        $this->historyService->setDefaultHistoryCount(10);
        $this->assertEquals(10, $this->historyService->getDefaultHistoryCount());
        
        // Test minimum enforcement
        $this->historyService->setDefaultHistoryCount(0);
        $this->assertEquals(1, $this->historyService->getDefaultHistoryCount());
    }
    
    public function test_pbkdf2_security_properties()
    {
        $password = 'TestPassword123!';
        
        // Test that multiple hashes of same password are different (due to unique salts)
        $hash1 = $this->hasher->hash($password);
        $hash2 = $this->hasher->hash($password);
        
        $this->assertNotEquals($hash1['salt'], $hash2['salt']);
        $this->assertNotEquals($hash1['hash'], $hash2['hash']);
        
        // But both should verify correctly
        $this->assertTrue($this->hasher->verify(
            $password,
            $hash1['salt'],
            $hash1['hash'],
            $hash1['iterations']
        ));
        
        $this->assertTrue($this->hasher->verify(
            $password,
            $hash2['salt'],
            $hash2['hash'],
            $hash2['iterations']
        ));
    }
    
    public function test_pbkdf2_handles_various_password_types()
    {
        $passwords = [
            'simple',
            'Complex!Password123',
            'Tëst🔒Pässwörd',
            '!@#$%^&*()_+{}[]|\\:";\'<>?,./',
            str_repeat('a', 1000), // Very long password
            '' // Empty password
        ];
        
        foreach ($passwords as $password) {
            $hash = $this->hasher->hash($password);
            
            $this->assertTrue($this->hasher->verify(
                $password,
                $hash['salt'],
                $hash['hash'],
                $hash['iterations']
            ), "Failed to verify password: " . substr($password, 0, 20) . "...");
            
            // Verify salt and hash lengths are consistent
            $decodedSalt = base64_decode($hash['salt']);
            $decodedHash = base64_decode($hash['hash']);
            
            $this->assertEquals(32, strlen($decodedSalt), "Salt length inconsistent for password");
            $this->assertEquals(64, strlen($decodedHash), "Hash length inconsistent for password");
        }
    }
}