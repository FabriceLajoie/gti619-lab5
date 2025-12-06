<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\AuditLog;
use App\Models\PasswordHistory;
use App\Services\PBKDF2PasswordHasher;
use App\Services\PasswordPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $regularUser;
    protected $adminRole;
    protected $userRole;
    protected $pbkdf2Hasher;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $this->adminRole = Role::create([
            'name' => 'Administrateur',
            'description' => 'Administrator role'
        ]);

        $this->userRole = Role::create([
            'name' => 'Préposé aux clients résidentiels',
            'description' => 'Residential client role'
        ]);

        // Create PBKDF2 hasher
        $this->pbkdf2Hasher = new PBKDF2PasswordHasher();

        // Create admin user with PBKDF2
        $adminHashData = $this->pbkdf2Hasher->hash('AdminPass123!');
        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => $adminHashData['hash'],
            'password_salt' => $adminHashData['salt'],
            'role_id' => $this->adminRole->id,
            'password_changed_at' => now(),
        ]);

        // Create regular user with PBKDF2
        $userHashData = $this->pbkdf2Hasher->hash('UserPass123!');
        $this->regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user@test.com',
            'password' => $userHashData['hash'],
            'password_salt' => $userHashData['salt'],
            'role_id' => $this->userRole->id,
            'password_changed_at' => now(),
        ]);
    }

    /** @test */
    public function admin_can_view_users_list()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.users'));

        $response->assertStatus(200);
        $response->assertSee($this->adminUser->name);
        $response->assertSee($this->regularUser->name);
    }

    /** @test */
    public function non_admin_cannot_view_users_list()
    {
        $response = $this->actingAs($this->regularUser)
            ->get(route('admin.users'));

        $response->assertStatus(302);
    }

    /** @test */
    public function admin_can_view_create_user_form()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.users.create'));

        $response->assertStatus(200);
        $response->assertSee('Créer un utilisateur');
    }

    /** @test */
    public function admin_can_create_user_with_pbkdf2_hashing()
    {
        // Set up re-authentication
        Session::put('reauth_verified_at', now());

        $userData = [
            'name' => 'New Test User',
            'email' => 'newuser@test.com',
            'password' => 'NewUserPass123!',
            'password_confirmation' => 'NewUserPass123!',
            'role_id' => $this->userRole->id,
            'must_change_password' => false,
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.users.store'), $userData);

        $response->assertRedirect(route('admin.users'));
        $response->assertSessionHas('success');

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'name' => 'New Test User',
            'email' => 'newuser@test.com',
            'role_id' => $this->userRole->id,
        ]);

        // Verify PBKDF2 hashing was used (salt should be present)
        $newUser = User::where('email', 'newuser@test.com')->first();
        $this->assertNotNull($newUser->password_salt);
        $this->assertNotEmpty($newUser->password_salt);

        // Verify password can be verified with PBKDF2
        $this->assertTrue(
            $this->pbkdf2Hasher->verify(
                'NewUserPass123!',
                $newUser->password_salt,
                $newUser->password,
                100000
            )
        );

        // Verify password history was created
        $this->assertDatabaseHas('password_histories', [
            'user_id' => $newUser->id,
        ]);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'user_created',
            'user_id' => $newUser->id,
        ]);
    }

    /** @test */
    public function user_creation_validates_password_complexity()
    {
        Session::put('reauth_verified_at', now());

        $userData = [
            'name' => 'New Test User',
            'email' => 'newuser@test.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
            'role_id' => $this->userRole->id,
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.users.store'), $userData);

        $response->assertSessionHasErrors('password');
        
        // Verify user was not created
        $this->assertDatabaseMissing('users', [
            'email' => 'newuser@test.com',
        ]);
    }

    /** @test */
    public function admin_can_view_user_details()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.users.details', $this->regularUser));

        $response->assertStatus(200);
        $response->assertSee($this->regularUser->name);
        $response->assertSee($this->regularUser->email);
    }

    /** @test */
    public function admin_can_edit_user()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.users.edit', $this->regularUser));

        $response->assertStatus(200);
        $response->assertSee($this->regularUser->name);
    }

    /** @test */
    public function admin_can_update_user()
    {
        Session::put('reauth_verified_at', now());

        $updateData = [
            'name' => 'Updated User Name',
            'email' => $this->regularUser->email,
            'role_id' => $this->userRole->id,
        ];

        $response = $this->actingAs($this->adminUser)
            ->put(route('admin.users.update', $this->regularUser), $updateData);

        $response->assertRedirect(route('admin.users'));
        $response->assertSessionHas('success');

        // Verify user was updated
        $this->assertDatabaseHas('users', [
            'id' => $this->regularUser->id,
            'name' => 'Updated User Name',
        ]);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'user_updated',
        ]);
    }

    /** @test */
    public function admin_can_unlock_locked_user()
    {
        // Lock the user
        $this->regularUser->update([
            'failed_login_attempts' => 5,
            'locked_until' => now()->addHours(1),
        ]);

        $this->assertTrue($this->regularUser->isLocked());

        Session::put('reauth_verified_at', now());

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.users.unlock', $this->regularUser));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify user was unlocked
        $this->regularUser->refresh();
        $this->assertFalse($this->regularUser->isLocked());
        $this->assertEquals(0, $this->regularUser->failed_login_attempts);
        $this->assertNull($this->regularUser->locked_until);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'account_unlocked',
        ]);
    }

    /** @test */
    public function admin_can_reset_user_password_with_pbkdf2()
    {
        Session::put('reauth_verified_at', now());

        $newPassword = 'NewSecurePass123!';
        $resetData = [
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
            'force_change' => false,
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.users.reset-password', $this->regularUser), $resetData);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify password was changed
        $this->regularUser->refresh();
        
        // Verify PBKDF2 hashing was used
        $this->assertNotNull($this->regularUser->password_salt);
        
        // Verify new password can be verified with PBKDF2
        $this->assertTrue(
            $this->pbkdf2Hasher->verify(
                $newPassword,
                $this->regularUser->password_salt,
                $this->regularUser->password,
                100000
            )
        );

        // Verify password_changed_at was updated
        $this->assertNotNull($this->regularUser->password_changed_at);

        // Verify password history was updated
        $this->assertDatabaseHas('password_histories', [
            'user_id' => $this->regularUser->id,
        ]);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'password_reset',
        ]);
    }

    /** @test */
    public function password_reset_validates_against_password_history()
    {
        Session::put('reauth_verified_at', now());

        // Get the current password
        $currentPassword = 'UserPass123!';

        // Try to reset to the same password
        $resetData = [
            'password' => $currentPassword,
            'password_confirmation' => $currentPassword,
        ];

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.users.reset-password', $this->regularUser), $resetData);

        $response->assertSessionHasErrors('password');
    }

    /** @test */
    public function admin_can_view_user_activity()
    {
        // Create some audit logs for the user
        AuditLog::create([
            'user_id' => $this->regularUser->id,
            'event_type' => 'login_success',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Browser',
            'details' => json_encode(['message' => 'User logged in']),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.users.activity', $this->regularUser));

        $response->assertStatus(200);
        $response->assertSee($this->regularUser->name);
        $response->assertSee('login_success');
    }

    /** @test */
    public function admin_can_filter_users_by_role()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.users', ['role_id' => $this->adminRole->id]));

        $response->assertStatus(200);
        $response->assertSee($this->adminUser->name);
    }

    /** @test */
    public function admin_can_filter_users_by_status()
    {
        // Lock a user
        $this->regularUser->update([
            'locked_until' => now()->addHours(1),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.users', ['status' => 'locked']));

        $response->assertStatus(200);
        $response->assertSee($this->regularUser->name);
    }

    /** @test */
    public function admin_can_search_users_by_name_or_email()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.users', ['search' => 'Regular']));

        $response->assertStatus(200);
        $response->assertSee($this->regularUser->name);
    }

    /** @test */
    public function complete_user_lifecycle_with_security_features()
    {
        Session::put('reauth_verified_at', now());

        // 1. Create user with PBKDF2 hashing
        $userData = [
            'name' => 'Lifecycle Test User',
            'email' => 'lifecycle@test.com',
            'password' => 'LifecyclePass123!',
            'password_confirmation' => 'LifecyclePass123!',
            'role_id' => $this->userRole->id,
            'must_change_password' => true,
        ];

        $createResponse = $this->actingAs($this->adminUser)
            ->post(route('admin.users.store'), $userData);

        $createResponse->assertRedirect(route('admin.users'));
        
        $user = User::where('email', 'lifecycle@test.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->must_change_password);

        // 2. View user details
        $detailsResponse = $this->actingAs($this->adminUser)
            ->get(route('admin.users.details', $user));

        $detailsResponse->assertStatus(200);

        // 3. Update user information
        $updateData = [
            'name' => 'Updated Lifecycle User',
            'email' => 'lifecycle@test.com',
            'role_id' => $this->userRole->id,
        ];

        $updateResponse = $this->actingAs($this->adminUser)
            ->put(route('admin.users.update', $user), $updateData);

        $updateResponse->assertRedirect(route('admin.users'));

        // 4. Lock user account (simulate failed login attempts)
        $user->update([
            'failed_login_attempts' => 5,
            'locked_until' => now()->addHours(1),
        ]);

        $this->assertTrue($user->isLocked());

        // 5. Unlock user account
        $unlockResponse = $this->actingAs($this->adminUser)
            ->post(route('admin.users.unlock', $user));

        $unlockResponse->assertRedirect();
        
        $user->refresh();
        $this->assertFalse($user->isLocked());

        // 6. Reset user password
        $resetData = [
            'password' => 'NewLifecyclePass123!',
            'password_confirmation' => 'NewLifecyclePass123!',
            'force_change' => false,
        ];

        $resetResponse = $this->actingAs($this->adminUser)
            ->post(route('admin.users.reset-password', $user), $resetData);

        $resetResponse->assertRedirect();

        // 7. Verify all audit logs were created
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event_type' => 'user_created',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'user_updated',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'account_unlocked',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'password_reset',
        ]);

        // 8. Verify password history
        $passwordHistories = PasswordHistory::where('user_id', $user->id)->get();
        $this->assertGreaterThanOrEqual(2, $passwordHistories->count());
    }
}
