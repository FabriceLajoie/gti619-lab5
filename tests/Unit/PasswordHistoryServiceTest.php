<?php

namespace Tests\Unit;

use App\Models\PasswordHistory;
use App\Models\User;
use App\Services\PBKDF2PasswordHasher;
use App\Services\PasswordHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordHistoryServiceTest extends TestCase
{
    use RefreshDatabase;
    
    private PasswordHistoryService $service;
    private PBKDF2PasswordHasher $hasher;
    private User $user;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->hasher = new PBKDF2PasswordHasher();
        $this->service = new PasswordHistoryService($this->hasher);
        
        // Create a test user
        $this->user = User::factory()->create();
    }
    
    public function test_add_to_history_creates_password_history_record()
    {
        $passwordData = $this->hasher->hash('TestPassword123!');
        
        $history = $this->service->addToHistory(
            $this->user,
            $passwordData['hash'],
            $passwordData['salt'],
            $passwordData['iterations'],
            $passwordData['algorithm']
        );
        
        $this->assertInstanceOf(PasswordHistory::class, $history);
        $this->assertEquals($this->user->id, $history->user_id);
        $this->assertEquals($passwordData['hash'], $history->password_hash);
        $this->assertEquals($passwordData['salt'], $history->salt);
        $this->assertEquals($passwordData['iterations'], $history->iterations);
        $this->assertEquals($passwordData['algorithm'], $history->algorithm);
        
        // Verify it's saved in database
        $this->assertDatabaseHas('password_histories', [
            'user_id' => $this->user->id,
            'password_hash' => $passwordData['hash'],
            'salt' => $passwordData['salt'],
            'iterations' => $passwordData['iterations'],
            'algorithm' => $passwordData['algorithm']
        ]);
    }
    
    public function test_is_password_reused_returns_true_for_reused_password()
    {
        $password = 'TestPassword123!';
        $passwordData = $this->hasher->hash($password);
        
        // Add password to history
        $this->service->addToHistory(
            $this->user,
            $passwordData['hash'],
            $passwordData['salt'],
            $passwordData['iterations']
        );
        
        // Check if password is reused
        $isReused = $this->service->isPasswordReused($this->user, $password);
        
        $this->assertTrue($isReused, '[FAIL] Password reuse detection should return true for reused password');
    }
    
    public function test_is_password_reused_returns_false_for_new_password()
    {
        $oldPassword = 'OldPassword123!';
        $newPassword = 'NewPassword456!';
        
        $passwordData = $this->hasher->hash($oldPassword);
        
        // Add old password to history
        $this->service->addToHistory(
            $this->user,
            $passwordData['hash'],
            $passwordData['salt'],
            $passwordData['iterations']
        );
        
        // Check if new password is reused
        $isReused = $this->service->isPasswordReused($this->user, $newPassword);
        
        $this->assertFalse($isReused, '[FAIL] Password reuse detection should return false for new password');
    }
    
    public function test_is_password_reused_respects_history_count_limit()
    {
        $passwords = [
            'Password1!',
            'Password2!',
            'Password3!',
            'Password4!',
            'Password5!',
            'Password6!'
        ];
        
        // Add 6 passwords to history
        foreach ($passwords as $password) {
            $passwordData = $this->hasher->hash($password);
            $this->service->addToHistory(
                $this->user,
                $passwordData['hash'],
                $passwordData['salt'],
                $passwordData['iterations']
            );
        }
        
        // First password should not be found when checking only last 5
        $isReused = $this->service->isPasswordReused($this->user, $passwords[0], 5);
        $this->assertFalse($isReused, '[FAIL] Oldest password should not be found when checking limited history');
        
        // Last password should be found
        $isReused = $this->service->isPasswordReused($this->user, $passwords[5], 5);
        $this->assertTrue($isReused, '[FAIL] Recent password should be found in history check');
    }
    
    public function test_get_password_history_returns_correct_records()
    {
        $passwords = ['Password1!', 'Password2!', 'Password3!'];
        
        // Add passwords to history
        foreach ($passwords as $password) {
            $passwordData = $this->hasher->hash($password);
            $this->service->addToHistory(
                $this->user,
                $passwordData['hash'],
                $passwordData['salt'],
                $passwordData['iterations']
            );
        }
        
        $history = $this->service->getPasswordHistory($this->user);
        
        $this->assertCount(3, $history);
        $this->assertEquals($this->user->id, $history->first()->user_id);
    }
    
    public function test_get_password_history_respects_limit()
    {
        $passwords = ['Password1!', 'Password2!', 'Password3!'];
        
        // Add passwords to history
        foreach ($passwords as $password) {
            $passwordData = $this->hasher->hash($password);
            $this->service->addToHistory(
                $this->user,
                $passwordData['hash'],
                $passwordData['salt'],
                $passwordData['iterations']
            );
        }
        
        $history = $this->service->getPasswordHistory($this->user, 2);
        
        $this->assertCount(2, $history);
    }
    
    public function test_get_password_history_returns_most_recent_first()
    {
        $password1Data = $this->hasher->hash('Password1!');
        $history1 = $this->service->addToHistory(
            $this->user,
            $password1Data['hash'],
            $password1Data['salt'],
            $password1Data['iterations']
        );
        
        // Wait a moment to ensure different timestamps
        usleep(1000);
        
        $password2Data = $this->hasher->hash('Password2!');
        $history2 = $this->service->addToHistory(
            $this->user,
            $password2Data['hash'],
            $password2Data['salt'],
            $password2Data['iterations']
        );
        
        $history = $this->service->getPasswordHistory($this->user);
        
        $this->assertEquals($history2->id, $history->first()->id);
        $this->assertEquals($history1->id, $history->last()->id);
    }
    
    public function test_cleanup_old_passwords_removes_excess_records()
    {
        $passwords = ['Password1!', 'Password2!', 'Password3!', 'Password4!', 'Password5!', 'Password6!', 'Password7!'];
        
        // Add 7 passwords to history (addToHistory automatically cleans up to keep 5)
        foreach ($passwords as $password) {
            $passwordData = $this->hasher->hash($password);
            $this->service->addToHistory(
                $this->user,
                $passwordData['hash'],
                $passwordData['salt'],
                $passwordData['iterations']
            );
        }
        
        // Should have 5 records after automatic cleanup
        $this->assertEquals(5, $this->service->getHistoryCount($this->user));
        
        // Manual cleanup with limit of 3 should remove 2 more records
        $deleted = $this->service->cleanupOldPasswords($this->user, 3);
        
        $this->assertEquals(2, $deleted);
        $this->assertEquals(3, $this->service->getHistoryCount($this->user));
    }
    
    public function test_get_history_count_returns_correct_count()
    {
        $this->assertEquals(0, $this->service->getHistoryCount($this->user));
        
        $passwordData = $this->hasher->hash('TestPassword123!');
        $this->service->addToHistory(
            $this->user,
            $passwordData['hash'],
            $passwordData['salt'],
            $passwordData['iterations']
        );
        
        $this->assertEquals(1, $this->service->getHistoryCount($this->user));
    }
    
    public function test_clear_history_removes_all_records()
    {
        $passwords = ['Password1!', 'Password2!', 'Password3!'];
        
        // Add passwords to history
        foreach ($passwords as $password) {
            $passwordData = $this->hasher->hash($password);
            $this->service->addToHistory(
                $this->user,
                $passwordData['hash'],
                $passwordData['salt'],
                $passwordData['iterations']
            );
        }
        
        $this->assertEquals(3, $this->service->getHistoryCount($this->user));
        
        $deleted = $this->service->clearHistory($this->user);
        
        $this->assertEquals(3, $deleted);
        $this->assertEquals(0, $this->service->getHistoryCount($this->user));
    }
    
    public function test_set_default_history_count_updates_setting()
    {
        $this->service->setDefaultHistoryCount(10);
        $this->assertEquals(10, $this->service->getDefaultHistoryCount());
    }
    
    public function test_set_default_history_count_enforces_minimum()
    {
        $this->service->setDefaultHistoryCount(0);
        $this->assertEquals(1, $this->service->getDefaultHistoryCount());
        
        $this->service->setDefaultHistoryCount(-5);
        $this->assertEquals(1, $this->service->getDefaultHistoryCount());
    }
    
    public function test_password_history_relationship_works()
    {
        $passwordData = $this->hasher->hash('TestPassword123!');
        $history = $this->service->addToHistory(
            $this->user,
            $passwordData['hash'],
            $passwordData['salt'],
            $passwordData['iterations']
        );
        
        // Test relationship from history to user
        $this->assertEquals($this->user->id, $history->user->id);
        
        // Test relationship from user to history
        $userHistories = $this->user->passwordHistories;
        $this->assertCount(1, $userHistories);
        $this->assertEquals($history->id, $userHistories->first()->id);
    }
    
    public function test_multiple_users_have_separate_histories()
    {
        $user2 = User::factory()->create();
        
        $password1Data = $this->hasher->hash('User1Password!');
        $password2Data = $this->hasher->hash('User2Password!');
        
        $this->service->addToHistory(
            $this->user,
            $password1Data['hash'],
            $password1Data['salt'],
            $password1Data['iterations']
        );
        
        $this->service->addToHistory(
            $user2,
            $password2Data['hash'],
            $password2Data['salt'],
            $password2Data['iterations']
        );
        
        $this->assertEquals(1, $this->service->getHistoryCount($this->user));
        $this->assertEquals(1, $this->service->getHistoryCount($user2));
        
        // User 1 should not have User 2's password in history
        $isReused = $this->service->isPasswordReused($this->user, 'User2Password!');
        $this->assertFalse($isReused);
    }
}