<?php

namespace Database\Seeders;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all users to use as teachers
        $users = User::all();

        if ($users->isEmpty()) {
            // Create a default user if none exists
            $defaultUser = User::create([
                'name' => 'Default Teacher',
                'email' => 'teacher@example.com',
                'password' => bcrypt('password'),
            ]);
            $teacherIds = [$defaultUser->id];
        } else {
            $teacherIds = $users->pluck('id')->toArray();
        }

        // Ensure we have at least 6 teacher IDs (repeat if necessary)
        while (count($teacherIds) < 6) {
            $teacherIds[] = $teacherIds[count($teacherIds) % count($teacherIds)];
        }

        $classes = [
            [
                'name' => 'Class 2025A',
                'generation' => '2025',
                'teacher_id' => $teacherIds[0] ?? null,
                'room' => 'B32',
                'students' => 32,
                'status' => 'Active',
            ],
            [
                'name' => 'Class 2025B',
                'generation' => '2025',
                'teacher_id' => $teacherIds[1] ?? null,
                'room' => 'B11',
                'students' => 30,
                'status' => 'Active',
            ],
            [
                'name' => 'Class 2026A',
                'generation' => '2026',
                'teacher_id' => $teacherIds[2] ?? null,
                'room' => 'B21',
                'students' => 28,
                'status' => 'Active',
            ],
            [
                'name' => 'Class 2026B',
                'generation' => '2026',
                'teacher_id' => $teacherIds[3] ?? null,
                'room' => 'A21',
                'students' => 29,
                'status' => 'Active',
            ],
            [
                'name' => 'Class 2027A',
                'generation' => '2027',
                'teacher_id' => $teacherIds[4] ?? null,
                'room' => 'A22',
                'students' => 25,
                'status' => 'Active',
            ],
            [
                'name' => 'Class 2027B',
                'generation' => '2027',
                'teacher_id' => $teacherIds[5] ?? null,
                'room' => 'B31',
                'students' => 24,
                'status' => 'Inactive',
            ],
        ];

        foreach ($classes as $class) {
            SchoolClass::create($class);
        }
    }
}
