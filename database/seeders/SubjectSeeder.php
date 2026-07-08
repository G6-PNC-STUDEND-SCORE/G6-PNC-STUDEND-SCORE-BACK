<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            ['name' => 'Mathematics', 'teacher' => 'Mr. John Smith', 'class' => '10A', 'status' => 'Active'],
            ['name' => 'English Language', 'teacher' => 'Ms. Sarah Johnson', 'class' => '10B', 'status' => 'Active'],
            ['name' => 'Science', 'teacher' => 'Dr. Michael Brown', 'class' => '11A', 'status' => 'Active'],
            ['name' => 'History', 'teacher' => 'Mrs. Emily Davis', 'class' => '11B', 'status' => 'Active'],
            ['name' => 'Geography', 'teacher' => 'Mr. John Smith', 'class' => '10A', 'status' => 'Active'],
            ['name' => 'Physics', 'teacher' => 'Dr. Michael Brown', 'class' => '12A', 'status' => 'Active'],
            ['name' => 'Chemistry', 'teacher' => 'Ms. Sarah Johnson', 'class' => '12B', 'status' => 'Inactive'],
            ['name' => 'Biology', 'teacher' => 'Mrs. Emily Davis', 'class' => '11A', 'status' => 'Active'],
        ];

        foreach ($subjects as $subject) {
            DB::table('subjects')->insert([
                'name' => $subject['name'],
                'teacher' => $subject['teacher'],
                'class' => $subject['class'],
                'status' => $subject['status'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
