<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
<<<<<<< HEAD
        $subjects = [
            ['name' => 'Mathematics', 'teacher' => 'Mr. John Smith', 'class' => '10A', 'is_active' => 'Active'],
            ['name' => 'English Language', 'teacher' => 'Ms. Sarah Johnson', 'class' => '10B', 'is_active' => 'Active'],
            ['name' => 'Science', 'teacher' => 'Dr. Michael Brown', 'class' => '11A', 'is_active' => 'Active'],
            ['name' => 'History', 'teacher' => 'Mrs. Emily Davis', 'class' => '11B', 'is_active' => 'Active'],
            ['name' => 'Geography', 'teacher' => 'Mr. John Smith', 'class' => '10A', 'is_active' => 'Active'],
            ['name' => 'Physics', 'teacher' => 'Dr. Michael Brown', 'class' => '12A', 'is_active' => 'Active'],
            ['name' => 'Chemistry', 'teacher' => 'Ms. Sarah Johnson', 'class' => '12B', 'is_active' => 'Inactive'],
            ['name' => 'Biology', 'teacher' => 'Mrs. Emily Davis', 'class' => '11A', 'is_active' => 'Active'],
=======
        $subjectNames = [
            'Logic', 'Typing', 'Algorithms', 'OOP',
            'English', 'Design', 'Data Analysis',
            'PL', 'Vue.js', 'PHP', 'Laravel', 'Node.js',
>>>>>>> 2f7a714627b59ef1f7770da653c5690dab9f8268
        ];

        foreach ($subjectNames as $name) {
            $exists = DB::table('subjects')
                ->where('name', $name)
                ->exists();

            if ($exists) continue;

            DB::table('subjects')->insert([
<<<<<<< HEAD
                'name' => $subject['name'],
                'teacher' => $subject['teacher'],
                'class' => $subject['class'],
                'is_active' => $subject['is_active'],
=======
                'subject_code' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 12)),
                'name'       => $name,
                'status'     => 'Active',
>>>>>>> 2f7a714627b59ef1f7770da653c5690dab9f8268
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
