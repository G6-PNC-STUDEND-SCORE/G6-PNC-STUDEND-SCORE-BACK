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
                'name' => 'Class A',
                'generation' => '2026',
                'teacher_id' => $teacherIds[0] ?? null,
                'room' => 'B12',
                'students' => 25,
                'status' => 'Active',
            ],
            [
                'name' => 'Class B',
                'generation' => '2026',
                'teacher_id' => $teacherIds[1] ?? null,
                'room' => 'B13',
                'students' => 25,
                'status' => 'Active',
            ],
            [
                'name' => 'Class C',
                'generation' => '2027',
                'teacher_id' => $teacherIds[2] ?? null,
                'room' => 'A12',
                'students' => 25,
                'status' => 'Active',
            ],
            [
                'name' => 'Class D',
                'generation' => '2027',
                'teacher_id' => $teacherIds[3] ?? null,
                'room' => 'A13',
                'students' => 25,
                'status' => 'Active',
            ],
            [
                'name' => 'WEB A',
                'generation' => '2026',
                'teacher_id' => $teacherIds[4] ?? null,
                'room' => 'B22',
                'students' => 25,
                'status' => 'Active',
            ],
            [
                'name' => 'WEB B',
                'generation' => '2026',
                'teacher_id' => $teacherIds[5] ?? null,
                'room' => 'B21',
                'students' => 25,
                'status' => 'Active',
            ],
            [
                'name' => 'WEB C',
                'generation' => '2027',
                'teacher_id' => $teacherIds[0] ?? null,
                'room' => 'B23',
                'students' => 25,
                'status' => 'Active',
            ],
            [
                'name' => 'WEB D',
                'generation' => '2027',
                'teacher_id' => $teacherIds[1] ?? null,
                'room' => 'B32',
                'students' => 25,
                'status' => 'Active',
            ],
            [
                'name' => 'Class A',
                'generation' => '2028',
                'teacher_id' => $teacherIds[2] ?? null,
                'room' => 'B31',
                'students' => 25,
                'status' => 'Active',
            ],
        ];

        foreach ($classes as $class) {
            SchoolClass::create($class);
        }
    }
}
