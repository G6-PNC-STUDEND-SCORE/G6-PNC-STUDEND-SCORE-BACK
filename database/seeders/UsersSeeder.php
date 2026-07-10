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
        $studentRole = Role::where('slug', 'student')->first();

        // Create Admin user
            $admin = User::firstOrCreate(
                ['email' => 'admin@gmail.com'],
                [
                    'name' => 'Admin',
                    'password' => Hash::make('12345678'),
                    'role_id' => $adminRole->id,
                    'status' => 'active',
                ]
            );

        // Create Teacher users
        $teacherEmails = [
            'yon@passerellesnumeriques.org' => 'Yon Teacher',
            'rady.y@passerellesnumeriques.org' => 'Rady Y',
            'him.hey@passerellesnumeriques.org' => 'Him Hey',
        ];

        foreach ($teacherEmails as $email => $name) {
            $teacher = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('12345678'),
                    'role_id' => $teacherRole->id,
                    'status' => 'active',
                ]
            );
        }

        // Create Student users
        $studentEmails = [
            'roeurn.ros@student.passerellesnumeriques.org' => 'Roeurn Ros',
            'sreyvik.von@student.passerellesnumeriques.org' => 'Sreyvik Von',
            'makara.pinn@student.passerellesnumeriques.org' => 'Makara Pinn',
            'makara.pon@student.passerellesnumeriques.org' => 'Makara Pon',
            'sreymao.lin@student.passerellesnumeriques.org' => 'Sreymao Lin',
            'ream.khorn@student.passerellesnumeriques.org' => 'Ream Khorn',
        ];

        foreach ($studentEmails as $email => $name) {
            $student = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('12345678'),
                    'role_id' => $studentRole->id,
                    'status' => 'active',
                ]
            );
        }
    }
}