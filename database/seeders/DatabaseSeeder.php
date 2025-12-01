<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Services\PBKDF2PasswordHasher;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the database.
     *
     * @return void
     */
    public function run()
    {
        // Initialize PBKDF2 password hasher
        $passwordHasher = new PBKDF2PasswordHasher();
        
        // Create roles
        $adminRole = Role::firstOrCreate(
            ['name' => 'Administrateur'],
            ['description' => 'Administrateur']
        );

        $residentRole = Role::firstOrCreate(
            ['name' => 'Préposé aux clients résidentiels'],
            ['description' => 'Préposé aux clients résidentiels']
        );

        $businessRole = Role::firstOrCreate(
            ['name' => "Préposé aux clients d'affaire"],
            ['description' => "Préposé aux clients d'affaire"]
        );

        // Create users with PBKDF2 hashed passwords
        $adminHashData = $passwordHasher->hash('password');
        $admin = User::firstOrCreate(
            ['email' => 'admin@ets.com'],
            [
                'name' => 'Administrateur',
                'password' => $adminHashData['hash'],
                'password_salt' => $adminHashData['salt'],
                'role_id' => $adminRole->id
            ]
        );

        $util1HashData = $passwordHasher->hash('password');
        $util1 = User::firstOrCreate(
            ['email' => 'utilisateur1@ets.com'],
            [
                'name' => 'Utilisateur1',
                'password' => $util1HashData['hash'],
                'password_salt' => $util1HashData['salt'],
                'role_id' => $residentRole->id
            ]
        );

        $util2HashData = $passwordHasher->hash('password');
        $util2 = User::firstOrCreate(
            ['email' => 'utilisateur2@ets.com'],
            [
                'name' => 'Utilisateur2',
                'password' => $util2HashData['hash'],
                'password_salt' => $util2HashData['salt'],
                'role_id' => $businessRole->id
            ]
        );

        // Seed clients
        $this->call(ClientSeeder::class);
        
        // Seed security configuration
        $this->call(SecurityConfigSeeder::class);
    }
}
