<?php

namespace App\Services;

use App\Models\PasswordHistory;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Password History Service
 * 
 * Manages password history tracking to prevent password reuse
 */
class PasswordHistoryService
{
    /**
     * PBKDF2 Password Hasher instance
     */
    private PBKDF2PasswordHasher $hasher;
    
    /**
     * Default number of passwords to keep in history
     */
    private int $defaultHistoryCount = 5;
    
    /**
     * Constructor
     * 
     * @param PBKDF2PasswordHasher $hasher
     */
    public function __construct(PBKDF2PasswordHasher $hasher)
    {
        $this->hasher = $hasher;
    }
    
    /**
     * Add a password to user's history
     * 
     * @param User $user The user
     * @param string $passwordHash Base64 encoded password hash
     * @param string $salt Base64 encoded salt
     * @param int $iterations Number of iterations used
     * @param string $algorithm Algorithm used for hashing
     * @return PasswordHistory The created password history record
     */
    public function addToHistory(
        User $user, 
        string $passwordHash, 
        string $salt, 
        int $iterations, 
        string $algorithm = 'sha256'
    ): PasswordHistory {
        // Create new password history record
        $passwordHistory = PasswordHistory::create([
            'user_id' => $user->id,
            'password_hash' => $passwordHash,
            'salt' => $salt,
            'iterations' => $iterations,
            'algorithm' => $algorithm
        ]);
        
        // Clean up old password history records
        $this->cleanupOldPasswords($user);
        
        return $passwordHistory;
    }
    
    /**
     * Check if a password has been used before by the user
     * 
     * @param User $user The user
     * @param string $password The plain text password to check
     * @param int $historyCount Number of previous passwords to check (default: 5)
     * @return bool True if password was used before, false otherwise
     */
    public function isPasswordReused(User $user, string $password, int $historyCount = null): bool
    {
        $historyCount = $historyCount ?? $this->defaultHistoryCount;
        
        // Get recent password history
        $passwordHistories = $this->getPasswordHistory($user, $historyCount);
        
        // Check against each historical password
        foreach ($passwordHistories as $history) {
            if ($this->hasher->verify(
                $password,
                $history->salt,
                $history->password_hash,
                $history->iterations
            )) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get password history for a user
     * 
     * @param User $user The user
     * @param int $limit Number of records to retrieve
     * @return Collection Collection of PasswordHistory records
     */
    public function getPasswordHistory(User $user, int $limit = null): Collection
    {
        $limit = $limit ?? $this->defaultHistoryCount;
        
        return PasswordHistory::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Clean up old password history records beyond the configured limit
     * 
     * @param User $user The user
     * @param int $keepCount Number of records to keep (default: 5)
     * @return int Number of records deleted
     */
    public function cleanupOldPasswords(User $user, int $keepCount = null): int
    {
        $keepCount = $keepCount ?? $this->defaultHistoryCount;
        
        // Get IDs of records to keep (most recent)
        $keepIds = PasswordHistory::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($keepCount)
            ->pluck('id');
        
        // Delete older records
        return PasswordHistory::where('user_id', $user->id)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }
    
    /**
     * Get the count of password history records for a user
     * 
     * @param User $user The user
     * @return int Number of password history records
     */
    public function getHistoryCount(User $user): int
    {
        return PasswordHistory::where('user_id', $user->id)->count();
    }
    
    /**
     * Clear all password history for a user
     * 
     * @param User $user The user
     * @return int Number of records deleted
     */
    public function clearHistory(User $user): int
    {
        return PasswordHistory::where('user_id', $user->id)->delete();
    }
    
    /**
     * Set the default history count
     * 
     * @param int $count Number of passwords to keep in history
     */
    public function setDefaultHistoryCount(int $count): void
    {
        $this->defaultHistoryCount = max(1, $count);
    }
    
    /**
     * Get the default history count
     * 
     * @return int Default history count
     */
    public function getDefaultHistoryCount(): int
    {
        return $this->defaultHistoryCount;
    }
}