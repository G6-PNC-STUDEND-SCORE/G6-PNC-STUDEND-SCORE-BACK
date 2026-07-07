<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $classIds = DB::table('classes')->pluck('id')->toArray();
        $subjectIds = DB::table('subjects')->pluck('id')->toArray();

        $firstNames = ['James', 'Mary', 'John', 'Patricia', 'Robert', 'Jennifer', 'Michael', 'Linda', 'David', 'Elizabeth',
                         'William', 'Barbara', 'Richard', 'Susan', 'Joseph', 'Jessica', 'Thomas', 'Sarah', 'Christopher', 'Karen',
                         'Daniel', 'Lisa', 'Matthew', 'Nancy', 'Anthony', 'Betty', 'Mark', 'Margaret', 'Donald', 'Sandra',
                         'Steven', 'Ashley', 'Paul', 'Kimberly', 'Andrew', 'Emily', 'Joshua', 'Donna', 'Kenneth', 'Michelle'];

        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez',
                        'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin',
                        'Lee', 'Perez', 'Thompson', 'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson'];

        $studentIds = [];

        // Create students and assign to classes
        foreach ($classIds as $classId) {
            $studentsInClass = rand(4, 6);

            for ($i = 0; $i < $studentsInClass; $i++) {
                $name = $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
                $gender = rand(0, 1) === 0 ? 'Male' : 'Female';

                $studentId = DB::table('students')->insertGetId([
                    'class_id' => $classId,
                    'name' => $name,
                    'photo' => null,
                    'gender' => $gender,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $studentIds[] = $studentId;
            }
        }

        // Create scores for each student for each subject
        foreach ($studentIds as $studentId) {
            foreach ($subjectIds as $subjectId) {
                $quiz = round(rand(50, 100) + rand(0, 99) / 100, 2);
                $assignment = round(rand(50, 100) + rand(0, 99) / 100, 2);
                $midterm = round(rand(40, 100) + rand(0, 99) / 100, 2);
                $final = round(rand(40, 100) + rand(0, 99) / 100, 2);
                $total = round(($quiz + $assignment + $midterm + $final) / 4, 2);

                // Determine grade based on total
                $grade = 'F';
                if ($total >= 90) $grade = 'A';
                elseif ($total >= 80) $grade = 'B';
                elseif ($total >= 70) $grade = 'C';
                elseif ($total >= 60) $grade = 'D';

                DB::table('scores')->insert([
                    'student_id' => $studentId,
                    'subject_id' => $subjectId,
                    'quiz' => $quiz,
                    'assignment' => $assignment,
                    'midterm' => $midterm,
                    'final' => $final,
                    'total' => $total,
                    'grade' => $grade,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
