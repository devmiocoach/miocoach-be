<?php

namespace Modules\Auth\Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AuthDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUser = User::firstOrCreate(
            ['email' => 'chef@miocoach.dev'],
            [
                'name' => 'Admin Chef',
                'password' => bcrypt('secretsauce'),
            ]
        );

        $adminUser->assignRole(Role::ADMIN->value);

        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('secretsauce'),
                'email_verified_at' => now(),
            ]
        );

        $user->assignRole(Role::USER->value);
    }
}
