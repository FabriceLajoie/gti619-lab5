<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PasswordPolicyService;
use App\Services\SecurityConfigService;
use App\Services\PasswordHistoryService;
use App\Models\User;
use App\Models\SecurityConfig;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PasswordPolicyServiceTest extends TestCase
{
    use RefreshDatabase;
    
    private PasswordPolicyService $passwordPolicyService;
    private SecurityConfigService $securityConfigService;
    private PasswordHistoryService $passwordHistoryService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create real service instances
        $this->securityConfigService = new SecurityConfigService();
        $this->passwordHistoryService = new PasswordHistoryService(new \App\Services\PBKDF2PasswordHasher());
        $this->passwordPolicyService = new PasswordPolicyService(
            $this->securityConfigService,
            $this->passwordHistoryService
        );
        
        // Create security config with default settings
        SecurityConfig::create([
            'password_min_length' => 12,
            'password_require_uppercase' => true,
            'password_require_lowercase' => true,
            'password_require_numbers' => true,
            'password_require_special' => true,
            'password_history_count' => 5,
            'password_expiry_days' => 90
        ]);
    }
    
    /** @test */
    public function it_validates_password_complexity_successfully()
    {
        $password = 'SecurePass123!';
        
        $result = $this->passwordPolicyService->validateComplexity($password);
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }
    
    /** @test */
    public function it_rejects_password_that_is_too_short()
    {
        $password = 'Short1!';
        
        $result = $this->passwordPolicyService->validateComplexity($password);
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Password must be at least 12 characters long', $result['errors']);
    }
    
    /** @test */
    public function it_rejects_password_without_uppercase()
    {
        $password = 'lowercase123!';
        
        $result = $this->passwordPolicyService->validateComplexity($password);
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Password must contain at least one uppercase letter (A-Z)', $result['errors']);
    }
    
    /** @test */
    public function it_rejects_password_without_lowercase()
    {
        $password = 'UPPERCASE123!';
        
        $result = $this->passwordPolicyService->validateComplexity($password);
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Password must contain at least one lowercase letter (a-z)', $result['errors']);
    }
    
    /** @test */
    public function it_rejects_password_without_numbers()
    {
        $password = 'NoNumbersHere!';
        
        $result = $this->passwordPolicyService->validateComplexity($password);
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Password must contain at least one number (0-9)', $result['errors']);
    }
    
    /** @test */
    public function it_rejects_password_without_special_characters()
    {
        $password = 'NoSpecialChars123';
        
        $result = $this->passwordPolicyService->validateComplexity($password);
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Password must contain at least one special character (!@#$%^&*()_+-=[]{}|;:,.<>?)', $result['errors']);
    }
    
    /** @test */
    public function it_rejects_password_that_is_too_long()
    {
        $password = str_repeat('A1!', 50); // 150 characters
        
        $result = $this->passwordPolicyService->validateComplexity($password);
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Password cannot exceed 128 characters', $result['errors']);
    }
    
    /** @test */
    public function it_rejects_common_weak_passwords()
    {
        $password = 'password';
        
        $result = $this->passwordPolicyService->validateComplexity($password);
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Password is too common and easily guessable', $result['errors']);
    }
    
    /** @test */
    public function it_rejects_passwords_with_keyboard_patterns()
    {
        $password = 'Qwerty12345!';
        
        $result = $this->passwordPolicyService->validateComplexity($password);
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Password contains keyboard patterns that are easily guessable', $result['errors']);
    }
    
    /** @test */
    public function it_rejects_passwords_with_repeated_characters()
    {
        $password = 'Aaaaa123!';
        
        $result = $this->passwordPolicyService->validateComplexity($password);
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Password cannot contain more than 3 repeated characters in a row', $result['errors']);
    }
    
    /** @test */
    public function it_rejects_passwords_with_simple_sequences()
    {
        $password = 'Abcd1234567!';
        
        $result = $this->passwordPolicyService->validateComplexity($password);
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Password cannot contain simple sequences (1234, abcd, etc.)', $result['errors']);
    }
    
    /** @test */
    public function it_validates_password_without_user_for_complexity_only()
    {
        $password = 'ValidComplexity123!';
        
        $result = $this->passwordPolicyService->validatePassword($password);
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }
    
    /** @test */
    public function it_generates_password_requirements_text()
    {
        $requirements = $this->passwordPolicyService->getPasswordRequirementsText();
        
        $this->assertIsArray($requirements);
        $this->assertContains('Must be at least 12 characters long', $requirements);
        $this->assertContains('Must contain at least one uppercase letter (A-Z)', $requirements);
        $this->assertContains('Must contain at least one lowercase letter (a-z)', $requirements);
        $this->assertContains('Must contain at least one number (0-9)', $requirements);
        $this->assertContains('Must contain at least one special character (!@#$%^&*()_+-=[]{}|;:,.<>?)', $requirements);
        $this->assertContains('Cannot be the same as any of your last 5 passwords', $requirements);
        $this->assertContains('Must be changed every 90 days', $requirements);
    }
    

    
    /** @test */
    public function it_validates_password_history_successfully_when_not_reused()
    {
        $user = User::factory()->create();
        $password = 'NewSecurePass123!';
        
        $result = $this->passwordPolicyService->validatePasswordHistory($password, $user);
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }
    
    /** @test */
    public function it_rejects_password_when_reused_from_history()
    {
        $user = User::factory()->create();
        $password = 'ReusedPassword123!';
        
        // Add password to history
        $hasher = new \App\Services\PBKDF2PasswordHasher();
        $hashData = $hasher->hash($password);
        $this->passwordHistoryService->addToHistory(
            $user, 
            $hashData['hash'], 
            $hashData['salt'], 
            $hashData['iterations']
        );
        
        $result = $this->passwordPolicyService->validatePasswordHistory($password, $user);
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Password cannot be the same as any of your last 5 passwords', $result['errors']);
    }
    
    /** @test */
    public function it_skips_history_validation_when_history_count_is_zero()
    {
        // Update config to disable password history
        SecurityConfig::first()->update(['password_history_count' => 0]);
        
        $user = User::factory()->create();
        $password = 'AnyPassword123!';
        
        $result = $this->passwordPolicyService->validatePasswordHistory($password, $user);
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }
    
    /** @test */
    public function it_validates_complete_password_successfully()
    {
        $user = User::factory()->create();
        $password = 'CompletelyValid123!';
        
        $result = $this->passwordPolicyService->validatePassword($password, $user);
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }
    
    /** @test */
    public function it_combines_complexity_and_history_errors()
    {
        $user = User::factory()->create();
        $password = 'short'; // Fails complexity
        
        // Add password to history (even though it's invalid)
        $hasher = new \App\Services\PBKDF2PasswordHasher();
        $hashData = $hasher->hash($password);
        $this->passwordHistoryService->addToHistory(
            $user, 
            $hashData['hash'], 
            $hashData['salt'], 
            $hashData['iterations']
        );
        
        $result = $this->passwordPolicyService->validatePassword($password, $user);
        
        $this->assertFalse($result['valid']);
        $this->assertGreaterThan(1, count($result['errors']));
    }
    
    /** @test */
    public function it_detects_expired_password()
    {
        $user = User::factory()->create([
            'password_changed_at' => Carbon::now()->subDays(100) // 100 days ago, expired (90 day limit)
        ]);
        
        $isExpired = $this->passwordPolicyService->isPasswordExpired($user);
        
        $this->assertTrue($isExpired);
    }
    
    /** @test */
    public function it_detects_non_expired_password()
    {
        $user = User::factory()->create([
            'password_changed_at' => Carbon::now()->subDays(30) // 30 days ago, not expired
        ]);
        
        $isExpired = $this->passwordPolicyService->isPasswordExpired($user);
        
        $this->assertFalse($isExpired);
    }
    
    /** @test */
    public function it_considers_password_expired_when_no_change_date()
    {
        $user = User::factory()->create([
            'password_changed_at' => null
        ]);
        
        $isExpired = $this->passwordPolicyService->isPasswordExpired($user);
        
        $this->assertTrue($isExpired);
    }
    
    /** @test */
    public function it_never_expires_password_when_expiry_disabled()
    {
        // Disable password expiry
        SecurityConfig::first()->update(['password_expiry_days' => 0]);
        
        $user = User::factory()->create([
            'password_changed_at' => Carbon::now()->subYears(10) // Very old password
        ]);
        
        $isExpired = $this->passwordPolicyService->isPasswordExpired($user);
        
        $this->assertFalse($isExpired);
    }
    
    /** @test */
    public function it_detects_forced_password_change()
    {
        $user = User::factory()->create([
            'must_change_password' => true
        ]);
        
        $mustChange = $this->passwordPolicyService->mustChangePassword($user);
        
        $this->assertTrue($mustChange);
    }
    
    /** @test */
    public function it_detects_password_change_needed_due_to_expiry()
    {
        $user = User::factory()->create([
            'must_change_password' => false,
            'password_changed_at' => Carbon::now()->subDays(100) // Expired
        ]);
        
        $mustChange = $this->passwordPolicyService->mustChangePassword($user);
        
        $this->assertTrue($mustChange);
    }
    
    /** @test */
    public function it_calculates_days_until_expiry()
    {
        $user = User::factory()->create([
            'password_changed_at' => Carbon::now()->subDays(60) // 30 days until expiry
        ]);
        
        $daysUntilExpiry = $this->passwordPolicyService->getDaysUntilExpiry($user);
        
        // Allow for 1 day variance due to timing differences
        $this->assertGreaterThanOrEqual(29, $daysUntilExpiry);
        $this->assertLessThanOrEqual(30, $daysUntilExpiry);
    }
    
    /** @test */
    public function it_returns_null_for_expired_password_days()
    {
        $user = User::factory()->create([
            'password_changed_at' => Carbon::now()->subDays(100) // Already expired
        ]);
        
        $daysUntilExpiry = $this->passwordPolicyService->getDaysUntilExpiry($user);
        
        $this->assertNull($daysUntilExpiry);
    }
    
    /** @test */
    public function it_returns_null_when_expiry_disabled()
    {
        // Disable password expiry
        SecurityConfig::first()->update(['password_expiry_days' => 0]);
        
        $user = User::factory()->create([
            'password_changed_at' => Carbon::now()->subDays(100)
        ]);
        
        $daysUntilExpiry = $this->passwordPolicyService->getDaysUntilExpiry($user);
        
        $this->assertNull($daysUntilExpiry);
    }
    
    /** @test */
    public function it_marks_password_as_changed()
    {
        $user = User::factory()->create([
            'must_change_password' => true,
            'password_changed_at' => Carbon::now()->subDays(100)
        ]);
        
        $this->passwordPolicyService->markPasswordChanged($user);
        
        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue($user->password_changed_at->isToday());
    }
    
    /** @test */
    public function it_forces_password_change()
    {
        $user = User::factory()->create([
            'must_change_password' => false
        ]);
        
        $this->passwordPolicyService->forcePasswordChange($user);
        
        $user->refresh();
        $this->assertTrue($user->must_change_password);
    }
    
    /** @test */
    public function it_handles_different_security_configurations()
    {
        // Update config to be less strict
        SecurityConfig::first()->update([
            'password_min_length' => 8,
            'password_require_uppercase' => false,
            'password_require_special' => false
        ]);
        
        $password = 'simplepass123';
        
        $result = $this->passwordPolicyService->validateComplexity($password);
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }
    
    /** @test */
    public function it_validates_edge_case_passwords()
    {
        $edgeCases = [
            'Exactly12Char!', // Exactly minimum length
            'Spaced Pass 123!', // Spaces
        ];
        
        foreach ($edgeCases as $password) {
            $result = $this->passwordPolicyService->validateComplexity($password);
            
            // All should be valid (assuming they meet requirements)
            if (strlen($password) >= 12 && strlen($password) <= 128) {
                $this->assertTrue($result['valid'], "Password '{$password}' should be valid");
            }
        }
    }
    
    /** @test */
    public function it_validates_multiple_complexity_errors()
    {
        $password = 'bad'; // Too short, no uppercase, no numbers, no special chars
        
        $result = $this->passwordPolicyService->validateComplexity($password);
        
        $this->assertFalse($result['valid']);
        $this->assertGreaterThan(3, count($result['errors']));
    }
    
    /** @test */
    public function it_accepts_valid_complex_passwords()
    {
        $validPasswords = [
            'MySecureP@ssw0rd123',
            'Anoth3r!ValidP@ss',
            'C0mpl3x&Str0ngP@ssw0rd',
            'V@lid123!P@ssw0rd'
        ];
        
        foreach ($validPasswords as $password) {
            $result = $this->passwordPolicyService->validateComplexity($password);
            $this->assertTrue($result['valid'], "Password '{$password}' should be valid but got errors: " . implode(', ', $result['errors']));
        }
    }
}