<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the database.
     *
     * @return void
     */
    public function run()
    {
        // Create roles
        $adminRole = Role::firstOrCreate(
            ['name' => 'Administrateur'],
            ['description' => 'Administrateur']
        );

        $residentRole = Role::firstOrCreate(
            ['name' => 'Préposé aux client résidentiels'],
            ['description' => 'Préposé aux client résidentiels']
        );

        $businessRole = Role::firstOrCreate(
            ['name' => "Préposé aux clients d'affaire"],
            ['description' => "Préposé aux clients d'affaire"]
        );

        // Create users and attach roles
        $admin = User::firstOrCreate(
            ['email' => 'admin@ets.com'],
            [
                'name' => 'Administrateur',
                'password' => Hash::make('password')
            ]
        );
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $util1 = User::firstOrCreate(
            ['email' => 'utilisateur1@ets.com'],
            [
                'name' => 'Utilisateur1',
                'password' => Hash::make('password')
            ]
        );
        $util1->roles()->syncWithoutDetaching([$residentRole->id]);

        $util2 = User::firstOrCreate(
            ['email' => 'utilisateur2@ets.com'],
            [
                'name' => 'Utilisateur2',
                'password' => Hash::make('password')
            ]
        );
        $util2->roles()->syncWithoutDetaching([$businessRole->id]);

        // Seed clients
        $this->call(ClientSeeder::class);
    }
}
