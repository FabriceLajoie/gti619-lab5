<?php

namespace Database\Seeders;

use App\Models\SecurityConfig;
use Illuminate\Database\Seeder;

class SecurityConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create default security configuration if it doesn't exist
        if (SecurityConfig::count() === 0) {
            SecurityConfig::create([
                // Authentication security parameters
                'max_login_attempts' => 5,
                'lockout_duration_minutes' => 30,
                
                // Password policy parameters
                'password_min_length' => 12,
                'password_require_uppercase' => true,
                'password_require_lowercase' => true,
                'password_require_numbers' => true,
                'password_require_special' => true,
                'password_history_count' => 5,
                'password_expiry_days' => 90,
                
                // PBKDF2 hashing parameters
                'pbkdf2_iterations' => 100000,
                
                // Session security parameters
                'session_timeout_minutes' => 120
            ]);

            $this->command->info('Default security configuration created successfully.');
        } else {
            $this->command->info('Security configuration already exists, skipping seeder.');
        }
    }
}