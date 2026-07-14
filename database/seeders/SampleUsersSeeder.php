<?php

namespace Database\Seeders;

use App\Models\RBAC\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SampleUsersSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('slug', 'admin')->first();
        $teacherRole = Role::where('slug', 'teacher')->first();
        $studentRole = Role::where('slug', 'student')->first();

        if (!$adminRole || !$teacherRole || !$studentRole) {
            $this->command->error('Roles not found. Run UsersSeeder first.');
            return;
        }

        $users = [
            // ── More Admin Users ──
            [
                'name' => 'Super Admin',
                'email' => 'super.admin@passerellesnumeriques.org',
                'role_id' => $adminRole->id,
                'gender' => 'Male',
                'phone' => '+855 12 345 678',
                'status' => 'active',
            ],
            [
                'name' => 'Admin Assistant',
                'email' => 'admin.assistant@passerellesnumeriques.org',
                'role_id' => $adminRole->id,
                'gender' => 'Female',
                'phone' => '+855 12 987 654',
                'status' => 'active',
            ],

            // ── More Teacher Users ──
            [
                'name' => 'Sokha Chea',
                'email' => 'sokha.chea@passerellesnumeriques.org',
                'role_id' => $teacherRole->id,
                'gender' => 'Female',
                'phone' => '+855 16 234 567',
                'status' => 'active',
            ],
            [
                'name' => 'Rithy Meas',
                'email' => 'rithy.meas@passerellesnumeriques.org',
                'role_id' => $teacherRole->id,
                'gender' => 'Male',
                'phone' => '+855 17 345 678',
                'status' => 'active',
            ],
            [
                'name' => 'Dara Chan',
                'email' => 'dara.chan@passerellesnumeriques.org',
                'role_id' => $teacherRole->id,
                'gender' => 'Male',
                'phone' => '+855 15 456 789',
                'status' => 'active',
            ],
            [
                'name' => 'Sreyneath Kong',
                'email' => 'sreyneath.kong@passerellesnumeriques.org',
                'role_id' => $teacherRole->id,
                'gender' => 'Female',
                'phone' => '+855 70 567 890',
                'status' => 'inactive',
            ],
            [
                'name' => 'Visal Heng',
                'email' => 'visal.heng@passerellesnumeriques.org',
                'role_id' => $teacherRole->id,
                'gender' => 'Male',
                'phone' => '+855 12 678 901',
                'status' => 'active',
            ],

            // ── More Student Users ──
            [
                'name' => 'Sophea Kim',
                'email' => 'sophea.kim@student.passerellesnumeriques.org',
                'role_id' => $studentRole->id,
                'gender' => 'Female',
                'phone' => '+855 96 789 012',
                'status' => 'active',
            ],
            [
                'name' => 'Bunthoeun Som',
                'email' => 'bunthoeun.som@student.passerellesnumeriques.org',
                'role_id' => $studentRole->id,
                'gender' => 'Male',
                'phone' => '+855 97 890 123',
                'status' => 'active',
            ],
            [
                'name' => 'Hout Heng',
                'email' => 'hout.heng@student.passerellesnumeriques.org',
                'role_id' => $studentRole->id,
                'gender' => 'Male',
                'phone' => '+855 10 901 234',
                'status' => 'suspended',
            ],
            [
                'name' => 'Srey Pich Tep',
                'email' => 'sreypich.tep@student.passerellesnumeriques.org',
                'role_id' => $studentRole->id,
                'gender' => 'Female',
                'phone' => '+855 11 012 345',
                'status' => 'active',
            ],
            [
                'name' => 'Vanthan Nou',
                'email' => 'vanthan.nou@student.passerellesnumeriques.org',
                'role_id' => $studentRole->id,
                'gender' => 'Male',
                'phone' => '+855 23 123 456',
                'status' => 'inactive',
            ],
            [
                'name' => 'Chantrea Son',
                'email' => 'chantrea.son@student.passerellesnumeriques.org',
                'role_id' => $studentRole->id,
                'gender' => 'Female',
                'phone' => '+855 18 234 567',
                'status' => 'active',
            ],
            [
                'name' => 'Sovann Vong',
                'email' => 'sovann.vong@student.passerellesnumeriques.org',
                'role_id' => $studentRole->id,
                'gender' => 'Male',
                'phone' => '+855 19 345 678',
                'status' => 'active',
            ],
            [
                'name' => 'Malis Phan',
                'email' => 'malis.phan@student.passerellesnumeriques.org',
                'role_id' => $studentRole->id,
                'gender' => 'Female',
                'phone' => '+855 12 456 789',
                'status' => 'suspended',
            ],
            [
                'name' => 'Ratanak Dy',
                'email' => 'ratanak.dy@student.passerellesnumeriques.org',
                'role_id' => $studentRole->id,
                'gender' => 'Male',
                'phone' => '+855 17 567 890',
                'status' => 'active',
            ],
            [
                'name' => 'Seiha Khim',
                'email' => 'seiha.khim@student.passerellesnumeriques.org',
                'role_id' => $studentRole->id,
                'gender' => 'Female',
                'phone' => '+855 15 678 901',
                'status' => 'active',
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('12345678'),
                    'role_id' => $userData['role_id'],
                    'gender' => $userData['gender'],
                    'phone' => $userData['phone'],
                    'status' => $userData['status'],
                ]
            );
        }

        $this->command->info('✓ ' . count($users) . ' sample users seeded successfully!');
    }
}
