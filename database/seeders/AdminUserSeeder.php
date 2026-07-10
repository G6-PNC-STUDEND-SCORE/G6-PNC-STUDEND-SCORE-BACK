<?php

namespace Database\Seeders;

use App\Models\RBAC\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure admin role exists
        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            [
                'name' => 'Administrator',
                'description' => 'Full system access',
            ]
        );

        // Also ensure teacher and student roles exist for use by other seeders
        Role::firstOrCreate(
            ['slug' => 'teacher'],
            [
                'name' => 'Teacher',
                'description' => 'Can manage classes, subjects, and scores',
            ]
        );
        Role::firstOrCreate(
            ['slug' => 'student'],
            [
                'name' => 'Student',
                'description' => 'Can view own scores and profile',
            ]
        );

        // Create admin user
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('12345678'),
            'status' => 'active',
        ]);

        // Assign admin role via pivot table
        $admin->roles()->attach($adminRole->id);
    }
}