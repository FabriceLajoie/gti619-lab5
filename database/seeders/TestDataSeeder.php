<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Services\PBKDF2PasswordHasher;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $passwordHasher = new PBKDF2PasswordHasher();

        // Create roles
        $adminRole = Role::create([
            'name' => 'Administrateur',
            'description' => 'Administrator with full access'
        ]);

        $residentialRole = Role::create([
            'name' => 'Préposé aux clients résidentiels',
            'description' => 'Residential client manager'
        ]);

        $businessRole = Role::create([
            'name' => 'Préposé aux clients d\'affaire',
            'description' => 'Business client manager'
        ]);

        // Create permissions
        $permissions = [
            ['name' => 'view_audit_logs', 'resource' => 'audit_logs', 'action' => 'view', 'description' => 'View audit logs'],
            ['name' => 'manage_users', 'resource' => 'users', 'action' => 'manage', 'description' => 'Manage users'],
            ['name' => 'manage_security_config', 'resource' => 'security_config', 'action' => 'manage', 'description' => 'Manage security configuration'],
            ['name' => 'view_residential_clients', 'resource' => 'clients', 'action' => 'view_residential', 'description' => 'View residential clients'],
            ['name' => 'view_business_clients', 'resource' => 'clients', 'action' => 'view_business', 'description' => 'View business clients'],
        ];

        foreach ($permissions as $permissionData) {
            Permission::create($permissionData);
        }

        // Assign permissions to admin role
        $adminRole->permissions()->attach(Permission::all());

        // Create test users
        $adminPassword = $passwordHasher->hash('Admin123!');
        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@ets.com',
            'password' => $adminPassword['hash'],
            'password_salt' => $adminPassword['salt'],
            'role_id' => $adminRole->id,
            'password_changed_at' => now(),
            'must_change_password' => false,
            'failed_login_attempts' => 0,
        ]);

        $userPassword = $passwordHasher->hash('User123!');
        $residentialUser = User::create([
            'name' => 'Residential User',
            'email' => 'residential@ets.com',
            'password' => $userPassword['hash'],
            'password_salt' => $userPassword['salt'],
            'role_id' => $residentialRole->id,
            'password_changed_at' => now(),
            'must_change_password' => false,
            'failed_login_attempts' => 0,
        ]);

        $businessPassword = $passwordHasher->hash('Business123!');
        $businessUser = User::create([
            'name' => 'Business User',
            'email' => 'business@ets.com',
            'password' => $businessPassword['hash'],
            'password_salt' => $businessPassword['salt'],
            'role_id' => $businessRole->id,
            'password_changed_at' => now(),
            'must_change_password' => false,
            'failed_login_attempts' => 0,
        ]);

        $this->command->info('Created test users:');
        $this->command->info("Admin: admin@ets.com / Admin123!");
        $this->command->info("Residential: residential@ets.com / User123!");
        $this->command->info("Business: business@ets.com / Business123!");
    }
}