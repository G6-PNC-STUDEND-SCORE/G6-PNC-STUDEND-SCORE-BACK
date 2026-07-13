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
        $teachers = DB::table('teachers')->get();
        $classes = DB::table('classes')->get();
        $assessmentTypeIds = DB::table('assessment_types')->pluck('id', 'code');

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

        // Create subject_offerings for each subject x term combination
        $offeringIds = [];
        foreach ($subjects as $subject) {
            foreach ($termIds as $termId) {
                $teacher = $teachers[($subject->id + $termId) % count($teachers)];
                $class = $classes[($subject->id + $termId) % count($classes)];

                $offering = DB::table('subject_offerings')
                    ->where('subject_id', $subject->id)
                    ->where('term_id', $termId)
                    ->where('class_id', $class->id)
                    ->where('generation_id', $generationId)
                    ->first();

                if (!$offering) {
                    $offeringId = DB::table('subject_offerings')->insertGetId([
                        'subject_id'    => $subject->id,
                        'teacher_id'    => $teacher->id,
                        'class_id'      => $class->id,
                        'generation_id' => $generationId,
                        'term_id'       => $termId,
                        'status'        => 'active',
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                    $offeringIds[$subject->id][$termId] = $offeringId;
                } else {
                    $offeringIds[$subject->id][$termId] = $offering->id;
                }
            }
        }

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

            DB::table('students')->insert([
                'user_id'                    => $user->id,
                'student_number_sequence_id' => $sequenceId,
                'generation_id'              => $generationId,
                'created_at'                 => now(),
                'updated_at'                 => now(),
            ]);

            $nextSeq++;
        }

        $students = DB::table('students')->get();

        // Specific mark profiles per student (by index)
        $profiles = [
            0 => ['name' => 'Roeurn Ros',   'quiz' => [85, 95], 'assignment' => [88, 95], 'midterm' => [88, 95], 'final' => [90, 98]],
            1 => ['name' => 'Sreyvik Von',  'quiz' => [78, 88], 'assignment' => [75, 85], 'midterm' => [75, 85], 'final' => [78, 88]],
            2 => ['name' => 'Makara Pinn',  'quiz' => [70, 80], 'assignment' => [68, 78], 'midterm' => [65, 78], 'final' => [70, 80]],
            3 => ['name' => 'Makara Pon',   'quiz' => [60, 72], 'assignment' => [58, 70], 'midterm' => [55, 68], 'final' => [58, 70]],
            4 => ['name' => 'Sreymao Lin',  'quiz' => [80, 92], 'assignment' => [82, 90], 'midterm' => [80, 90], 'final' => [83, 93]],
            5 => ['name' => 'Ream Khorn',   'quiz' => [50, 65], 'assignment' => [50, 63], 'midterm' => [48, 62], 'final' => [50, 65]],
        ];

        $randInRange = fn(array $range) => round(rand($range[0] * 100, $range[1] * 100) / 100, 2);

        // Create scores per student, per subject, per term
        foreach ($students as $index => $student) {
            $profile = $profiles[$index % count($profiles)];

            foreach ($subjects as $subject) {
                foreach ($termIds as $termId) {
                    $offeringId = $offeringIds[$subject->id][$termId] ?? null;
                    if (!$offeringId) continue;

                    // Create enrollment
                    $enrollment = DB::table('student_subject_enrollments')
                        ->where('student_id', $student->id)
                        ->where('subject_offering_id', $offeringId)
                        ->first();

                    if (!$enrollment) {
                        $enrollmentId = DB::table('student_subject_enrollments')->insertGetId([
                            'student_id'          => $student->id,
                            'subject_offering_id' => $offeringId,
                            'status'              => 'enrolled',
                            'created_at'          => now(),
                            'updated_at'          => now(),
                        ]);
                    } else {
                        $enrollmentId = $enrollment->id;
                    }

                    // Check if score already exists for this enrollment
                    if (DB::table('scores')->where('student_subject_enrollment_id', $enrollmentId)->exists()) {
                        continue;
                    }

                    $scoreId = DB::table('scores')->insertGetId([
                        'student_subject_enrollment_id' => $enrollmentId,
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
                            'score_id'            => $scoreId,
                            'assessment_type_id'  => $assessmentTypeIds[$detail['type']],
                            'label'               => $detail['label'],
                            'mark'                => $detail['mark'],
                            'max_score'           => 100,
                            'created_at'          => now(),
                            'updated_at'          => now(),
                        ]);
                    }

                    // Formula: Quiz avg × 20% + Assignment × 10% + Midterm × 30% + Final × 40%
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

        $this->seedClassHistories($students, $classes, $generationId);
        $this->seedReportCardsAndTranscripts($students, $generationId, $termIds);
    }

    private function seedClassHistories($students, $classes, int $generationId): void
    {
        if ($classes->isEmpty()) {
            return;
        }

        foreach ($students as $index => $student) {
            $class = $classes[$index % $classes->count()];

            DB::table('student_class_histories')->updateOrInsert(
                [
                    'student_id' => $student->id,
                    'class_id' => $class->id,
                    'generation_id' => $generationId,
                    'status' => 'active',
                ],
                [
                    'start_date' => now()->subMonths(10)->toDateString(),
                    'end_date' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function seedReportCardsAndTranscripts($students, int $generationId, array $termIds): void
    {
        $adminId = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('roles.slug', 'admin')
            ->value('users.id');

        foreach ($students as $student) {
            $reportCardIds = [];

            foreach ($termIds as $termId) {
                $scores = DB::table('scores')
                    ->join('student_subject_enrollments', 'scores.student_subject_enrollment_id', '=', 'student_subject_enrollments.id')
                    ->join('subject_offerings', 'student_subject_enrollments.subject_offering_id', '=', 'subject_offerings.id')
                    ->where('student_subject_enrollments.student_id', $student->id)
                    ->where('subject_offerings.generation_id', $generationId)
                    ->where('subject_offerings.term_id', $termId)
                    ->select(
                        'scores.id as score_id',
                        'scores.total',
                        'scores.grade',
                        'scores.remarks',
                        'subject_offerings.id as subject_offering_id'
                    )
                    ->get();

                if ($scores->isEmpty()) {
                    continue;
                }

                $average = round((float) $scores->avg('total'), 2);
                $reportCard = DB::table('report_cards')
                    ->where('student_id', $student->id)
                    ->where('generation_id', $generationId)
                    ->where('term_id', $termId)
                    ->first();

                if ($reportCard) {
                    $reportCardId = $reportCard->id;
                    DB::table('report_cards')->where('id', $reportCardId)->update([
                        'total_average' => $average,
                        'grade' => $this->gradeFromAverage($average),
                        'total_students' => $students->count(),
                        'generated_by' => $adminId,
                        'generated_at' => now()->subDays(rand(1, 15)),
                        'updated_at' => now(),
                    ]);
                } else {
                    $reportCardId = DB::table('report_cards')->insertGetId([
                        'student_id' => $student->id,
                        'generation_id' => $generationId,
                        'term_id' => $termId,
                        'total_average' => $average,
                        'rank_in_class' => null,
                        'total_students' => $students->count(),
                        'grade' => $this->gradeFromAverage($average),
                        'remarks' => $average >= 60 ? 'Generated from seeded scores.' : 'Academic support recommended.',
                        'generated_by' => $adminId,
                        'generated_at' => now()->subDays(rand(1, 15)),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $reportCardIds[] = $reportCardId;

                foreach ($scores as $score) {
                    DB::table('report_card_details')->updateOrInsert(
                        [
                            'report_card_id' => $reportCardId,
                            'subject_offering_id' => $score->subject_offering_id,
                        ],
                        [
                            'score_id' => $score->score_id,
                            'subject_average' => $score->total,
                            'grade' => $score->grade,
                            'remarks' => $score->remarks,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }

            foreach ($termIds as $termId) {
                $cards = DB::table('report_cards')
                    ->where('generation_id', $generationId)
                    ->where('term_id', $termId)
                    ->orderByDesc('total_average')
                    ->get();

                foreach ($cards as $rank => $card) {
                    DB::table('report_cards')->where('id', $card->id)->update([
                        'rank_in_class' => $rank + 1,
                    ]);
                }
            }

            $studentCards = DB::table('report_cards')
                ->where('student_id', $student->id)
                ->where('generation_id', $generationId)
                ->orderBy('term_id')
                ->get();

            if ($studentCards->isEmpty()) {
                continue;
            }

            $overallAverage = round((float) $studentCards->avg('total_average'), 2);
            $transcript = DB::table('transcripts')
                ->where('student_id', $student->id)
                ->where('generation_id', $generationId)
                ->first();

            if ($transcript) {
                $transcriptId = $transcript->id;
                DB::table('transcripts')->where('id', $transcriptId)->update([
                    'overall_average' => $overallAverage,
                    'overall_grade' => $this->gradeFromAverage($overallAverage),
                    'status' => 'final',
                    'generated_by' => $adminId,
                    'generated_at' => now()->subDays(rand(1, 7)),
                    'updated_at' => now(),
                ]);
            } else {
                $transcriptId = DB::table('transcripts')->insertGetId([
                    'student_id' => $student->id,
                    'generation_id' => $generationId,
                    'overall_average' => $overallAverage,
                    'overall_grade' => $this->gradeFromAverage($overallAverage),
                    'status' => 'final',
                    'generated_by' => $adminId,
                    'generated_at' => now()->subDays(rand(1, 7)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($studentCards as $card) {
                DB::table('transcript_details')->updateOrInsert(
                    [
                        'transcript_id' => $transcriptId,
                        'term_id' => $card->term_id,
                        'report_card_id' => $card->id,
                    ],
                    [
                        'term_average' => $card->total_average,
                        'term_grade' => $card->grade,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    private function gradeFromAverage(float $average): string
    {
        return match (true) {
            $average >= 90 => 'A',
            $average >= 80 => 'B',
            $average >= 70 => 'C',
            $average >= 60 => 'D',
            default => 'F',
        };
    }
}
