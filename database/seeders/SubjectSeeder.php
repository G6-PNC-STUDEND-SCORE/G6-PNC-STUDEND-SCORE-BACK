<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            ['name' => 'Mathematics', 'teacher' => 'Mr. John Smith', 'class' => '10A', 'is_active' => 'Active'],
            ['name' => 'English Language', 'teacher' => 'Ms. Sarah Johnson', 'class' => '10B', 'is_active' => 'Active'],
            ['name' => 'Science', 'teacher' => 'Dr. Michael Brown', 'class' => '11A', 'is_active' => 'Active'],
            ['name' => 'History', 'teacher' => 'Mrs. Emily Davis', 'class' => '11B', 'is_active' => 'Active'],
            ['name' => 'Geography', 'teacher' => 'Mr. John Smith', 'class' => '10A', 'is_active' => 'Active'],
            ['name' => 'Physics', 'teacher' => 'Dr. Michael Brown', 'class' => '12A', 'is_active' => 'Active'],
            ['name' => 'Chemistry', 'teacher' => 'Ms. Sarah Johnson', 'class' => '12B', 'is_active' => 'Inactive'],
            ['name' => 'Biology', 'teacher' => 'Mrs. Emily Davis', 'class' => '11A', 'is_active' => 'Active'],
        ];

        foreach ($subjects as $subject) {
            DB::table('subjects')->insert([
                'name' => $subject['name'],
                'teacher' => $subject['teacher'],
                'class' => $subject['class'],
                'is_active' => $subject['is_active'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
