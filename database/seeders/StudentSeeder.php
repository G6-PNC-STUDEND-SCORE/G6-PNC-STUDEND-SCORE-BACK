<?php

namespace Database\Seeders;

use App\Models\RBAC\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentNumberService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $classIds = DB::table('classes')->pluck('id')->toArray();
        $subjectIds = DB::table('subjects')->pluck('id')->toArray();

        // Ensure academic year exists
        $academicYearId = DB::table('academic_years')->insertGetId([
            'name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'is_current' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Get the student role (created by AdminUserSeeder)
        $studentRole = Role::where('slug', 'student')->firstOrFail();

        $firstNames = ['James', 'Mary', 'John', 'Patricia', 'Robert', 'Jennifer', 'Michael', 'Linda', 'David', 'Elizabeth',
                         'William', 'Barbara', 'Richard', 'Susan', 'Joseph', 'Jessica', 'Thomas', 'Sarah', 'Christopher', 'Karen',
                         'Daniel', 'Lisa', 'Matthew', 'Nancy', 'Anthony', 'Betty', 'Mark', 'Margaret', 'Donald', 'Sandra',
                         'Steven', 'Ashley', 'Paul', 'Kimberly', 'Andrew', 'Emily', 'Joshua', 'Donna', 'Kenneth', 'Michelle'];

        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez',
                        'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin',
                        'Lee', 'Perez', 'Thompson', 'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson'];

        $studentService = app(StudentNumberService::class);
        $intakeYear = 2025;
        $studentIndex = 1;

        // Create students and assign to classes
        foreach ($classIds as $classId) {
            $studentsInClass = rand(4, 6);

            for ($i = 0; $i < $studentsInClass; $i++) {
                $name = $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
                $gender = rand(0, 1) === 0 ? 'Male' : 'Female';
                $email = 'student' . $studentIndex . '@school.edu';

                // Create the user account
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make('password123'),
                    'gender' => $gender,
                    'status' => 'active',
                ]);

                // Assign student role via pivot table
                $user->roles()->attach($studentRole->id);

                // Generate student number and create student record
                $studentNumberData = $studentService->generateNext($intakeYear);

                $student = Student::create([
                    'user_id' => $user->id,
                    'student_number' => $studentNumberData['student_number'],
                    'intake_year' => $studentNumberData['intake_year'],
                    'sequence_number' => $studentNumberData['sequence_number'],
                    'class_id' => $classId,
                    'academic_year_id' => $academicYearId,
                    'enrollment_date' => '2025-09-01',
                ]);

                $studentIndex++;

                // Create scores for this student for each subject
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
                        'student_id' => $student->id,
                        'subject_id' => $subjectId,
                        'academic_year_id' => $academicYearId,
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
}
