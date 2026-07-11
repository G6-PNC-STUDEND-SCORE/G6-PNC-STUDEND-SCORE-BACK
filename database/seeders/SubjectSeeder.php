<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $teachers = DB::table('teachers')->get();

        // Subjects grouped per teacher (matched by index)
        $subjectsByTeacher = [
            0 => ['Logic', 'Typing', 'Algorithms', 'OOP'],
            1 => ['English', 'Design', 'Data Analysis'],
            2 => ['PL', 'Vue.js', 'PHP', 'Laravel', 'Node.js'],
        ];

        foreach ($teachers as $index => $teacher) {
            $names = $subjectsByTeacher[$index % count($subjectsByTeacher)];

            // Get first class belonging to this teacher
            $class = DB::table('classes')->where('teacher_id', $teacher->id)->first();
            $classId = $class->id ?? null;

            foreach ($names as $name) {
                $exists = DB::table('subjects')
                    ->where('name', $name)
                    ->where('teacher_id', $teacher->id)
                    ->exists();

                if ($exists) continue;

                DB::table('subjects')->insert([
                    'name'       => $name,
                    'teacher_id' => $teacher->id,
                    'class_id'   => $classId,
                    'status'     => 'Active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
