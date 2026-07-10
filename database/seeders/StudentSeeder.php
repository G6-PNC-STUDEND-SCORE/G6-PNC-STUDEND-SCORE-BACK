<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = DB::table('subjects')->get();

        $currentYear = 2026;
        $generation = DB::table('generations')->where('year', $currentYear)->first();
        if (!$generation) {
            $generationId = DB::table('generations')->insertGetId([
                'year'       => $currentYear,
                'is_current' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $generationId = $generation->id;
        }

        // Seed 4 terms
        $termDefs = [
            1 => ['name' => 'Term 1', 'start_date' => '2025-09-01', 'end_date' => '2025-11-30'],
            2 => ['name' => 'Term 2', 'start_date' => '2025-12-01', 'end_date' => '2026-02-28'],
            3 => ['name' => 'Term 3', 'start_date' => '2026-03-01', 'end_date' => '2026-04-30'],
            4 => ['name' => 'Term 4', 'start_date' => '2026-05-01', 'end_date' => '2026-06-30'],
        ];

        $termIds = [];
        foreach ($termDefs as $number => $def) {
            $term = DB::table('terms')->where('term_number', $number)->first();
            if (!$term) {
                $termIds[$number] = DB::table('terms')->insertGetId([
                    'term_number' => $number,
                    'name'        => $def['name'],
                    'start_date'  => $def['start_date'],
                    'end_date'    => $def['end_date'],
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            } else {
                $termIds[$number] = $term->id;
            }
        }

        // Classes already seeded by TeacherSeeder
        $classIds = DB::table('classes')->pluck('id')->toArray();

        // Get student users
        $studentUsers = User::whereHas('role', function ($query) {
            $query->where('slug', 'student');
        })->get();

        $intakeYear = 2026;
        $nextSeq = DB::table('student_number_sequences')
            ->where('intake_year', $intakeYear)
            ->count() + 1;

        foreach ($studentUsers as $user) {
            if (DB::table('students')->where('user_id', $user->id)->exists()) {
                continue;
            }

            $studentNumber = sprintf('PNC%d-%03d', $intakeYear, $nextSeq);

            $sequenceId = DB::table('student_number_sequences')->insertGetId([
                'intake_year'    => $intakeYear,
                'student_number' => $studentNumber,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $classId = $classIds[($nextSeq - 1) % count($classIds)] ?? null;

            DB::table('students')->insert([
                'user_id'                    => $user->id,
                'student_number_sequence_id' => $sequenceId,
                'generation_id'              => $generationId,
                'class_id'                   => $classId,
                'created_at'                 => now(),
                'updated_at'                 => now(),
            ]);

            $nextSeq++;
        }

        $students = DB::table('students')->get();

        // Specific mark profiles per student (by index)
        // Format: [ quiz_range, assignment_range, midterm_range, final_range ]
        $profiles = [
            0 => ['name' => 'Roeurn Ros',   'quiz' => [85, 95], 'assignment' => [88, 95], 'midterm' => [88, 95], 'final' => [90, 98]], // top student
            1 => ['name' => 'Sreyvik Von',  'quiz' => [78, 88], 'assignment' => [75, 85], 'midterm' => [75, 85], 'final' => [78, 88]], // above average
            2 => ['name' => 'Makara Pinn',  'quiz' => [70, 80], 'assignment' => [68, 78], 'midterm' => [65, 78], 'final' => [70, 80]], // average
            3 => ['name' => 'Makara Pon',   'quiz' => [60, 72], 'assignment' => [58, 70], 'midterm' => [55, 68], 'final' => [58, 70]], // below average
            4 => ['name' => 'Sreymao Lin',  'quiz' => [80, 92], 'assignment' => [82, 90], 'midterm' => [80, 90], 'final' => [83, 93]], // high performer
            5 => ['name' => 'Ream Khorn',   'quiz' => [50, 65], 'assignment' => [50, 63], 'midterm' => [48, 62], 'final' => [50, 65]], // struggling
        ];

        $randInRange = fn(array $range) => round(rand($range[0] * 100, $range[1] * 100) / 100, 2);

        // Create scores per student, per subject, per term
        foreach ($students as $index => $student) {
            $profile = $profiles[$index % count($profiles)];

            foreach ($subjects as $subject) {
                foreach ($termIds as $termId) {
                    if (DB::table('scores')->where('student_id', $student->id)->where('subject_id', $subject->id)->where('term_id', $termId)->exists()) {
                        continue;
                    }

                    $scoreId = DB::table('scores')->insertGetId([
                        'student_id' => $student->id,
                        'subject_id' => $subject->id,
                        'term_id'    => $termId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $q1   = $randInRange($profile['quiz']);
                    $q2   = $randInRange($profile['quiz']);
                    $q3   = $randInRange($profile['quiz']);
                    $asgn = $randInRange($profile['assignment']);
                    $mid  = $randInRange($profile['midterm']);
                    $fin  = $randInRange($profile['final']);

                    $details = [
                        ['type' => 'quiz',       'label' => 'Quiz 1',    'mark' => $q1],
                        ['type' => 'quiz',       'label' => 'Quiz 2',    'mark' => $q2],
                        ['type' => 'quiz',       'label' => 'Quiz 3',    'mark' => $q3],
                        ['type' => 'assignment', 'label' => 'Assignment','mark' => $asgn],
                        ['type' => 'midterm',    'label' => 'Midterm',   'mark' => $mid],
                        ['type' => 'final',      'label' => 'Final',     'mark' => $fin],
                    ];

                    foreach ($details as $detail) {
                        DB::table('score_details')->insert([
                            'score_id'   => $scoreId,
                            'type'       => $detail['type'],
                            'label'      => $detail['label'],
                            'mark'       => $detail['mark'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    // Formula: Quiz avg×20% + Assignment×10% + Midterm×30% + Final×40%
                    $quizAvg = round(($q1 + $q2 + $q3) / 3, 2);
                    $total   = round(
                        ($quizAvg * 0.20) +
                        ($asgn    * 0.10) +
                        ($mid     * 0.30) +
                        ($fin     * 0.40),
                        2
                    );

                    $grade = match (true) {
                        $total >= 90 => 'A',
                        $total >= 80 => 'B',
                        $total >= 70 => 'C',
                        $total >= 60 => 'D',
                        default      => 'F',
                    };

                    DB::table('scores')->where('id', $scoreId)->update([
                        'total' => $total,
                        'grade' => $grade,
                    ]);
                }
            }
        }
    }
}
