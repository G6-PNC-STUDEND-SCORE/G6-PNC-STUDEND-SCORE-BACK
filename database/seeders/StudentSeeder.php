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
        $assessmentTypes = DB::table('assessment_types')->get()->keyBy('code');
        $assessmentTypeIds = $assessmentTypes->pluck('id', 'code');
        $quizWeight = ($assessmentTypes->get('quiz')->weight_percent ?? 20) / 100;
        $assignmentWeight = ($assessmentTypes->get('assignment')->weight_percent ?? 10) / 100;
        $midtermWeight = ($assessmentTypes->get('midterm')->weight_percent ?? 30) / 100;
        $finalWeight = ($assessmentTypes->get('final')->weight_percent ?? 40) / 100;

        $currentYear = 2026;
        $generation = DB::table('generations')->where('year', $currentYear)->first();
        if (!$generation) {
            $generationId = DB::table('generations')->insertGetId([
                'name' => 'Batch ' . $currentYear,
                'year' => $currentYear,
                'is_current' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $generationId = $generation->id;
        }

        $academicYear = DB::table('academic_years')->where('year', $currentYear)->first();
        if (!$academicYear) {
            $academicYearId = DB::table('academic_years')->insertGetId([
                'year' => $currentYear,
                'name' => 'Academic Year ' . $currentYear,
                'is_current' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $academicYearId = $academicYear->id;
        }

        $termDefs = [
            1 => ['name' => 'Term 1', 'start_date' => '2025-09-01', 'end_date' => '2025-11-30'],
            2 => ['name' => 'Term 2', 'start_date' => '2025-12-01', 'end_date' => '2026-02-28'],
            3 => ['name' => 'Term 3', 'start_date' => '2026-03-01', 'end_date' => '2026-04-30'],
            4 => ['name' => 'Term 4', 'start_date' => '2026-05-01', 'end_date' => '2026-06-30'],
        ];

        $termIds = [];
        foreach ($termDefs as $number => $def) {
            $term = DB::table('terms')
                ->where('term_number', $number)
                ->where('academic_year_id', $academicYearId)
                ->first();
            if (!$term) {
                $termIds[$number] = DB::table('terms')->insertGetId([
                    'academic_year_id' => $academicYearId,
                    'term_number' => $number,
                    'name' => $def['name'],
                    'start_date' => $def['start_date'],
                    'end_date' => $def['end_date'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $termIds[$number] = $term->id;
            }
        }

        // ── Subject-term mapping (inline so this seeder works independently) ──
        // Term number => list of subject names
        $termSubjectNames = [
            1 => ['Logic', 'BCU', 'MS Office', 'English', 'PL', 'Design'],
            2 => ['Web design', 'Algorithms', 'Git', 'English for IT', 'PL'],
            3 => ['PHP', 'Database', 'Javascript', 'QA', 'English', 'English for IT', 'PL'],
            4 => ['Laravel', 'Node.js', 'Vue.js', 'OOP', 'Typescript'],
        ];

        // Build a lookup: subject_name => subject_id
        $subjectNameToId = [];
        foreach ($subjects as $s) {
            $subjectNameToId[$s->name] = $s->id;
        }

        // Build lookup: "subject_id-term_id" => true for valid combinations
        $validCombinations = [];
        foreach ($termSubjectNames as $termNumber => $names) {
            $termId = $termIds[$termNumber] ?? null;
            if (!$termId) continue;
            foreach ($names as $name) {
                $sid = $subjectNameToId[$name] ?? null;
                if ($sid) {
                    $validCombinations[$sid . '-' . $termId] = true;
                }
            }
        }

        $offeringIds = [];
        foreach ($subjects as $subject) {
            foreach ($termIds as $termId) {
                // Only create offerings for valid subject-term combinations
                if (!isset($validCombinations[$subject->id . '-' . $termId])) {
                    continue;
                }
                $teacher = $teachers[($subject->id + $termId) % count($teachers)];
                $class = $classes[($subject->id + $termId) % count($classes)];

                $offering = DB::table('subject_offerings')
                    ->where('subject_id', $subject->id)
                    ->where('term_id', $termId)
                    ->where('class_id', $class->id)
                    ->where('academic_year_id', $academicYearId)
                    ->first();

                if (!$offering) {
                    $offeringId = DB::table('subject_offerings')->insertGetId([
                        'subject_id' => $subject->id,
                        'teacher_id' => $teacher->id,
                        'class_id' => $class->id,
                        'term_id' => $termId,
                        'academic_year_id' => $academicYearId,
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $offeringIds[$subject->id][$termId] = $offeringId;
                } else {
                    $offeringIds[$subject->id][$termId] = $offering->id;
                }
            }
        }

        $studentUsers = User::whereHas('role', function ($query) {
            $query->where('slug', 'student');
        })->get();

        $seq = 1;
        foreach ($studentUsers as $user) {
            if (DB::table('students')->where('user_id', $user->id)->exists()) {
                continue;
            }
            $studentNumber = sprintf('PNC%d-%03d', $currentYear, $seq);
            DB::table('students')->insertGetId([
                'user_id' => $user->id,
                'student_id_number' => $studentNumber,
                'generation_id' => $generationId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $seq++;
        }

        $students = DB::table('students')->get();
        $histories = DB::table('student_class_histories')
            ->where('generation_id', $generationId)
            ->get()
            ->groupBy('student_id');

        $profiles = [
            0 => ['quiz' => [85, 95], 'assignment' => [88, 95], 'midterm' => [88, 95], 'final' => [90, 98]],
            1 => ['quiz' => [78, 88], 'assignment' => [75, 85], 'midterm' => [75, 85], 'final' => [78, 88]],
            2 => ['quiz' => [70, 80], 'assignment' => [68, 78], 'midterm' => [65, 78], 'final' => [70, 80]],
            3 => ['quiz' => [60, 72], 'assignment' => [58, 70], 'midterm' => [55, 68], 'final' => [58, 70]],
            4 => ['quiz' => [80, 92], 'assignment' => [82, 90], 'midterm' => [80, 90], 'final' => [83, 93]],
            5 => ['quiz' => [50, 65], 'assignment' => [50, 63], 'midterm' => [48, 62], 'final' => [50, 65]],
        ];

        $randInRange = fn(array $range) => round(rand($range[0] * 100, $range[1] * 100) / 100, 2);

        foreach ($students as $i => $student) {
            $profile = $profiles[$i % count($profiles)];
            $studentHistories = $histories->get($student->id, collect());
            $classHistory = $studentHistories->first();

            if (!$classHistory) continue;

            foreach ($subjects as $subject) {
                foreach ($termIds as $termId) {
                    $offeringId = $offeringIds[$subject->id][$termId] ?? null;
                    if (!$offeringId) continue;

                    $enrollment = DB::table('student_subject_enrollments')
                        ->where('student_class_history_id', $classHistory->id)
                        ->where('subject_offering_id', $offeringId)
                        ->first();

                    if (!$enrollment) {
                        $enrollmentId = DB::table('student_subject_enrollments')->insertGetId([
                            'student_class_history_id' => $classHistory->id,
                            'subject_offering_id' => $offeringId,
                            'status' => 'enrolled',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        $enrollmentId = $enrollment->id;
                    }

                    if (DB::table('scores')->where('student_subject_enrollment_id', $enrollmentId)->exists()) {
                        continue;
                    }

                    $q1 = $randInRange($profile['quiz']);
                    $q2 = $randInRange($profile['quiz']);
                    $q3 = $randInRange($profile['quiz']);
                    $asgn = $randInRange($profile['assignment']);
                    $mid = $randInRange($profile['midterm']);
                    $fin = $randInRange($profile['final']);

                    $quizAvg = round(($q1 + $q2 + $q3) / 3, 2);
                    $total = round(($quizAvg * $quizWeight) + ($asgn * $assignmentWeight) + ($mid * $midtermWeight) + ($fin * $finalWeight), 2);

                    $passFail = $total >= 60 ? 'pass' : 'fail';
                    $grade = match(true) {
                        $total >= 90 => 'A',
                        $total >= 80 => 'B',
                        $total >= 70 => 'C',
                        $total >= 60 => 'D',
                        default => 'F',
                    };

                    $scoreId = DB::table('scores')->insertGetId([
                        'student_subject_enrollment_id' => $enrollmentId,
                        'quiz_total' => $quizAvg,
                        'assignment_total' => $asgn,
                        'midterm_score' => $mid,
                        'final_exam_score' => $fin,
                        'total_weighted_score' => $total,
                        'grade' => $grade,
                        'pass_fail' => $passFail,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $details = [
                        ['type' => 'quiz', 'label' => 'Quiz 1', 'score' => $q1, 'seq' => 1],
                        ['type' => 'quiz', 'label' => 'Quiz 2', 'score' => $q2, 'seq' => 2],
                        ['type' => 'quiz', 'label' => 'Quiz 3', 'score' => $q3, 'seq' => 3],
                        ['type' => 'assignment', 'label' => 'Assignment', 'score' => $asgn, 'seq' => 1],
                        ['type' => 'midterm', 'label' => 'Midterm', 'score' => $mid, 'seq' => 1],
                        ['type' => 'final', 'label' => 'Final Exam', 'score' => $fin, 'seq' => 1],
                    ];

                    foreach ($details as $detail) {
                        DB::table('score_details')->insert([
                            'score_id' => $scoreId,
                            'assessment_type_id' => $assessmentTypeIds[$detail['type']],
                            'label' => $detail['label'],
                            'score' => $detail['score'],
                            'sequence_number' => $detail['seq'],
                            'max_score' => 100,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }

        $this->seedClassHistories($students, $classes, $generationId);
        $this->seedReportCardsAndTranscripts($students, $generationId, $termIds);
    }

    private function seedClassHistories($students, $classes, int $generationId): void
    {
        if ($classes->isEmpty()) return;

        $academicYear = DB::table('academic_years')->where('is_current', true)->first();
        $academicYearId = $academicYear->id ?? $generationId;

        foreach ($students as $index => $student) {
            $class = $classes[$index % $classes->count()];
            DB::table('student_class_histories')->updateOrInsert(
                [
                    'student_id' => $student->id,
                    'class_id' => $class->id,
                    'generation_id' => $generationId,
                ],
                [
                    'academic_year_id' => $academicYearId,
                    'start_date' => now()->subMonths(10)->toDateString(),
                    'end_date' => null,
                    'status' => 'active',
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
            foreach ($termIds as $termId) {
                $scores = DB::table('scores')
                    ->join('student_subject_enrollments', 'scores.student_subject_enrollment_id', '=', 'student_subject_enrollments.id')
                    ->join('student_class_histories', 'student_subject_enrollments.student_class_history_id', '=', 'student_class_histories.id')
                    ->join('subject_offerings', 'student_subject_enrollments.subject_offering_id', '=', 'subject_offerings.id')
                    ->where('student_class_histories.student_id', $student->id)
                    ->where('subject_offerings.term_id', $termId)
                    ->select('scores.id as score_id', 'scores.total_weighted_score', 'scores.grade', 'scores.pass_fail', 'scores.remarks', 'subject_offerings.id as subject_offering_id')
                    ->get();

                if ($scores->isEmpty()) continue;

                $average = round((float) $scores->avg('total_weighted_score'), 2);
                $reportCard = DB::table('report_cards')->where('student_id', $student->id)->where('term_id', $termId)->first();

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

                foreach ($scores as $score) {
                    DB::table('report_card_details')->updateOrInsert(
                        ['report_card_id' => $reportCardId, 'subject_offering_id' => $score->subject_offering_id],
                        ['score_id' => $score->score_id, 'total_score' => $score->total_weighted_score, 'grade' => $score->grade, 'remarks' => $score->remarks, 'created_at' => now(), 'updated_at' => now()]
                    );
                }
            }

            foreach ($termIds as $termId) {
                $cards = DB::table('report_cards')->where('term_id', $termId)->orderByDesc('total_average')->get();
                foreach ($cards as $rank => $card) {
                    DB::table('report_cards')->where('id', $card->id)->update(['rank_in_class' => $rank + 1]);
                }
            }

            $studentCards = DB::table('report_cards')
                ->where('student_id', $student->id)
                ->where('generation_id', $generationId)
                ->orderBy('term_id')
                ->get();

            if ($studentCards->isEmpty()) continue;

            $overallAverage = round((float) $studentCards->avg('total_average'), 2);
            $transcript = DB::table('transcripts')->where('student_id', $student->id)->where('generation_id', $generationId)->first();

            if ($transcript) {
                DB::table('transcripts')->where('id', $transcript->id)->update([
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
                    ['transcript_id' => $transcriptId ?? $transcript->id, 'term_id' => $card->term_id],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    private function gradeFromAverage(float $average): string
    {
        return match(true) {
            $average >= 90 => 'A',
            $average >= 80 => 'B',
            $average >= 70 => 'C',
            $average >= 60 => 'D',
            default => 'F',
        };
    }
}
