<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        // Create sample teachers
        $teachers = [
            [
                'name' => 'Sarah Johnson',
                'email' => 'sarah.johnson@school.edu',
                'password' => Hash::make('password123'),
                'role' => 'teacher',
                'avatar' => null,
            ],
            [
                'name' => 'Michael Chen',
                'email' => 'michael.chen@school.edu',
                'password' => Hash::make('password123'),
                'role' => 'teacher',
                'avatar' => null,
            ],
            [
                'name' => 'Emily Rodriguez',
                'email' => 'emily.rodriguez@school.edu',
                'password' => Hash::make('password123'),
                'role' => 'teacher',
                'avatar' => null,
            ],
        ];

        foreach ($teachers as $teacher) {
            User::create($teacher);
        }

        // Create classes for each teacher
        $teacherUsers = User::where('role', 'teacher')->get();
        $classNameIndex = 1;

        foreach ($teacherUsers as $teacher) {
            DB::table('classes')->insert([
                [
                    'name' => 'Class ' . str_pad($classNameIndex++, 2, '0', STR_PAD_LEFT) . ' - ' . $teacher->name,
                    'teacher_id' => $teacher->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Class ' . str_pad($classNameIndex++, 2, '0', STR_PAD_LEFT) . ' - ' . $teacher->name,
                    'teacher_id' => $teacher->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }
}
