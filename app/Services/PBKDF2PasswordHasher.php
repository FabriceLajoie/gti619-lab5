<?php

namespace App\Services;

use Exception;

class PBKDF2PasswordHasher
{
    /**
     * Default number of iterations for PBKDF2
     */
    private int $iterations;
    
    /**
     * Salt length in bytes (32 bytes = 256 bits)
     */
    private int $saltLength = 32;
    
    /**
     * Hash output length in bytes (64 bytes = 512 bits)
     */
    private int $hashLength = 64;
    
    /**
     * Hash algorithm to use
     */
    private string $algorithm = 'sha256';
    
    /**
     * Constructor
     * 
     * @param int $iterations Number of PBKDF2 iterations (default: 100,000)
     */
    public function __construct(int $iterations = 100000)
    {
        $this->iterations = $iterations;
    }
    
    /**
     * Hash a password using PBKDF2
     * 
     * @param string $password The plain text password to hash
     * @return array Array containing salt, hash, and iterations
     * @throws Exception If random bytes generation fails
     */
    public function hash(string $password): array
    {
        // Generate cryptographically secure salt
        $salt = $this->generateSalt();
        
        // Apply PBKDF2 with multiple iterations
        $hash = $this->pbkdf2($password, $salt, $this->iterations);
        
        return [
            'salt' => base64_encode($salt),
            'hash' => base64_encode($hash),
            'iterations' => $this->iterations,
            'algorithm' => $this->algorithm
        ];
    }
    
    /**
     * Verify a password against stored hash data
     * 
     * @param string $password The plain text password to verify
     * @param string $storedSalt Base64 encoded salt
     * @param string $storedHash Base64 encoded hash
     * @param int $iterations Number of iterations used for the stored hash
     * @return bool True if password matches, false otherwise
     */
    public function verify(string $password, string $storedSalt, string $storedHash, int $iterations): bool
    {
        try {
            // Decode stored salt and hash
            $salt = base64_decode($storedSalt, true);
            $expectedHash = base64_decode($storedHash, true);
            
            // Validate decoded data
            if ($salt === false || $expectedHash === false) {
                return false;
            }
            
            // Recompute hash with same parameters
            $computedHash = $this->pbkdf2($password, $salt, $iterations);
            
            // Timing-safe comparison to prevent timing attacks
            return hash_equals($expectedHash, $computedHash);
            
        } catch (Exception $e) {
            // Log error in production, return false for security
            return false;
        }
    }
    
    /**
     * Generate secure salt
     * 
     * @return string Raw binary salt
     * @throws Exception If random bytes generation fails
     */
    private function generateSalt(): string
    {
        $salt = random_bytes($this->saltLength);
        
        if ($salt === false || strlen($salt) !== $this->saltLength) {
            throw new Exception('Failed to generate secure salt');
        }
        
        return $salt;
    }
    
    /**
     * Apply PBKDF2 key derivation function
     * 
     * @param string $password The password to hash
     * @param string $salt The salt to use
     * @param int $iterations Number of iterations
     * @return string Raw binary hash
     */
    private function pbkdf2(string $password, string $salt, int $iterations): string
    {
        return hash_pbkdf2(
            $this->algorithm,
            $password,
            $salt,
            $iterations,
            $this->hashLength,
            true // Return raw binary data
        );
    }
    
    /**
     * Get current configuration
     * 
     * @return array Configuration parameters
     */
    public function getConfig(): array
    {
        return [
            'iterations' => $this->iterations,
            'salt_length' => $this->saltLength,
            'hash_length' => $this->hashLength,
            'algorithm' => $this->algorithm
        ];
    }
    
    /**
     * Set number of iterations
     * 
     * @param int $iterations Number of iterations (minimum 10,000)
     * @throws Exception If iterations is too low
     */
    public function setIterations(int $iterations): void
    {
        if ($iterations < 10000) {
            throw new Exception('Iterations must be at least 10,000 for security');
        }
        
        $this->iterations = $iterations;
    }
}