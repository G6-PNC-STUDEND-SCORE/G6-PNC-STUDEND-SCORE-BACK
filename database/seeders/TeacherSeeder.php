<?php

namespace Database\Seeders;

use App\Models\RBAC\Role;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        // Get the teacher role (created by AdminUserSeeder)
        $teacherRole = Role::where('slug', 'teacher')->firstOrFail();

        // Create sample teachers
        $teacherData = [
            [
                'name' => 'Sarah Johnson',
                'email' => 'sarah.johnson@school.edu',
                'code' => 'TCH-001',
            ],
            [
                'name' => 'Michael Chen',
                'email' => 'michael.chen@school.edu',
                'code' => 'TCH-002',
            ],
            [
                'name' => 'Emily Rodriguez',
                'email' => 'emily.rodriguez@school.edu',
                'code' => 'TCH-003',
            ],
        ];

        $teacherRecords = [];

        foreach ($teacherData as $data) {
            // Create the user account
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make('password123'),
                'status' => 'active',
            ]);

            // Assign teacher role via pivot table
            $user->roles()->attach($teacherRole->id);

            // Create the teacher profile record with teacher_code
            $teacher = Teacher::create([
                'user_id' => $user->id,
                'teacher_code' => $data['code'],
                'position' => 'lecturer',
            ]);

            $teacherRecords[] = $teacher;
        }

        // Create classes for each teacher
        $classNumber = 1;

        foreach ($teacherRecords as $teacher) {
            DB::table('classes')->insert([
                [
                    'name' => 'Class ' . str_pad($classNumber, 2, '0', STR_PAD_LEFT),
                    'code' => 'CLS-' . str_pad($classNumber, 3, '0', STR_PAD_LEFT),
                    'teacher_id' => $teacher->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Class ' . str_pad($classNumber + 1, 2, '0', STR_PAD_LEFT),
                    'code' => 'CLS-' . str_pad($classNumber + 1, 3, '0', STR_PAD_LEFT),
                    'teacher_id' => $teacher->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $classNumber += 2;
        }
    }
}
