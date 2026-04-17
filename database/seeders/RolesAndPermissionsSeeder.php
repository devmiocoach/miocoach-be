<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Users
            'users.view', 'users.create', 'users.edit', 'users.delete',
            // WorkoutPlans
            'workout-plans.view', 'workout-plans.create', 'workout-plans.edit', 'workout-plans.delete',
            // Exercises
            'exercises.view', 'exercises.create', 'exercises.edit', 'exercises.delete',
            // Bookings
            'bookings.view', 'bookings.create', 'bookings.edit', 'bookings.delete',
            // Payments
            'payments.view',
            // Progress
            'progress.view', 'progress.create',
            // Reviews
            'reviews.view', 'reviews.create', 'reviews.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::all());

        $coach = Role::firstOrCreate(['name' => 'coach', 'guard_name' => 'web']);
        $coach->syncPermissions([
            'workout-plans.view', 'workout-plans.create', 'workout-plans.edit', 'workout-plans.delete',
            'exercises.view', 'exercises.create', 'exercises.edit',
            'bookings.view', 'bookings.create', 'bookings.edit',
            'progress.view',
            'reviews.view',
        ]);

        $client = Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $client->syncPermissions([
            'workout-plans.view',
            'exercises.view',
            'bookings.view', 'bookings.create',
            'progress.view', 'progress.create',
            'reviews.view', 'reviews.create',
        ]);
    }
}
