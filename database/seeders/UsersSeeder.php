<?php

namespace Database\Seeders;

use App\Models\RBAC\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure roles exist
        $roles = [
            'admin' => 'Administrator with full system access',
            'teacher' => 'Teacher with teaching permissions',
            'student' => 'Student with limited permissions',
        ];

        foreach ($roles as $slug => $description) {
            Role::firstOrCreate(
                ['slug' => $slug],
                ['name' => ucfirst($slug), 'description' => $description]
            );
        }

        // Get roles
        $adminRole = Role::where('slug', 'admin')->first();
        $teacherRole = Role::where('slug', 'teacher')->first();

        // Create Admin user
            User::updateOrCreate(
                ['email' => 'admin@gmail.com'],
                [
                    'name' => 'Admin',
                    'password' => Hash::make('12345678'),
                    'role_id' => $adminRole->id,
                    'status' => 'active',
                    'gender' => 'Male',
                ]
            );

        // Create Teacher users
        $teacherEmails = [
            'yon.yen@passerellesnumeriques.org' => 'Yon Teacher',
            'rady.y@passerellesnumeriques.org' => 'Rady Y',
            'him.hey@passerellesnumeriques.org' => 'Him Hey',
            'mengheang.pho@passerellesnumeriques.org' => 'Meangheang Pho',
        ];

        $teacherGenders = [
            'yon.yen@passerellesnumeriques.org' => 'Male',
            'rady.y@passerellesnumeriques.org' => 'Male',
            'him.hey@passerellesnumeriques.org' => 'Male',
            'mengheang.pho@passerellesnumeriques.org' => 'Male',
        ];

        foreach ($teacherEmails as $email => $name) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('12345678'),
                    'role_id' => $teacherRole->id,
                    'status' => 'active',
                    'gender' => $teacherGenders[$email] ?? 'Male',
                ]
            );
        }
    }
}