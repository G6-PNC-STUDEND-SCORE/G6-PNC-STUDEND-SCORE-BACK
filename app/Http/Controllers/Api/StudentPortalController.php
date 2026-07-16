<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class StudentPortalController extends Controller
{
    private const CREDITS_PER_SUBJECT = 3;
    private const TOTAL_CREDITS = 120;

    /**
     * Resolve the authenticated student (must be linked to a Student record).
     */
    private function student(Request $request): ?Student
    {
        /** @var User $user */
        $user = $request->user();
        return $user->student;
    }

    private function gradePoints(?string $grade): float
    {
        return match (strtoupper((string) $grade)) {
            'A+', 'A' => 4.0,
            'A-' => 3.7,
            'B+' => 3.3,
            'B' => 3.0,
            'B-' => 2.7,
            'C+' => 2.3,
            'C' => 2.0,
            'C-' => 1.7,
            'D+' => 1.3,
            'D' => 1.0,
            default => 0.0,
        };
    }

    private function gradeFromTotal(?float $total): string
    {
        $t = (float) $total;
        return match (true) {
            $t >= 90 => 'A',
            $t >= 80 => 'B+',
            $t >= 70 => 'B',
            $t >= 60 => 'C',
            $t >= 50 => 'D',
            default => 'F',
        };
    }

    /**
     * Full portal payload for the logged-in student.
     */
    public function portal(Request $request): JsonResponse
    {
        $student = $this->student($request);
        if (!$student) {
            return response()->json(['message' => 'No student record linked to this account.'], 403);
        }

        $student->load([
            'user',
            'generation',
            'studentNumberSequence',
            'enrollments.subjectOffering.subject',
            'enrollments.subjectOffering.teacher.user',
            'enrollments.subjectOffering.term',
            'enrollments.score.details.assessmentType',
            'enrollments.score.quizzes',
            'enrollments.score.assignments',
            'enrollments.score.midterms',
            'enrollments.score.finals',
        ]);

        $enrollments = $student->enrollments;
        $currentTerm = $this->currentTerm($enrollments);

        $profile = [
            'name' => $student->user?->name,
            'studentId' => $student->student_number,
            'email' => $student->user?->email,
            'class' => $this->currentClass($student, $currentTerm),
            'generation' => $student->generation?->name,
            'department' => $student->generation?->name,
            'currentTerm' => $currentTerm?->name,
            'academicStatus' => ucfirst($student->user?->status ?? 'active'),
            'avatar' => $student->user?->avatar,
        ];

        $scored = $enrollments->filter(fn ($e) => $e->score && $e->score->total !== null);
        $average = $scored->count() ? round($scored->avg(fn ($e) => $e->score->total), 2) : 0;
        $gpa = $scored->count() ? round($scored->avg(fn ($e) => $this->gradePoints($e->score->grade ?? $this->gradeFromTotal($e->score->total))), 2) : 0;

        $summary = [
            ['label' => 'Current GPA', 'value' => $gpa, 'decimals' => 2, 'icon' => 'bi bi-speedometer2', 'iconClass' => 'icon-blue', 'subtitle' => 'Out of 4.00'],
            ['label' => 'Overall Average', 'value' => $average, 'decimals' => 1, 'icon' => 'bi bi-graph-up-arrow', 'iconClass' => 'icon-green', 'subtitle' => 'All subjects'],
            ['label' => 'Current Subjects', 'value' => $currentTerm ? $enrollments->where('subjectOffering.term_id', $currentTerm->id)->count() : $enrollments->count(), 'decimals' => 0, 'icon' => 'bi bi-book-half', 'iconClass' => 'icon-violet', 'subtitle' => 'This term'],
            ['label' => 'Credits Completed', 'value' => $enrollments->count() * self::CREDITS_PER_SUBJECT, 'decimals' => 0, 'icon' => 'bi bi-patch-check', 'iconClass' => 'icon-orange', 'subtitle' => 'of ' . self::TOTAL_CREDITS . ' credits'],
        ];

        $termTrends = $this->termTrends($enrollments);

        $currentEnrollments = $currentTerm
            ? $enrollments->where('subjectOffering.term_id', $currentTerm->id)
            : $enrollments;

        $currentSubjects = $currentEnrollments->map(function ($e) {
            $score = $e->score;
            $total = $score?->total;
            $grade = $score?->grade ?? ($total !== null ? $this->gradeFromTotal($total) : null);
            $details = $score?->details;
            $progress = $details && $details->count()
                ? min(100, round($details->whereNotNull('mark')->count() / $details->count() * 100))
                : ($total !== null ? 100 : 0);

            return [
                'id' => $e->id,
                'name' => $e->subjectOffering?->subject?->name,
                'teacher' => $e->subjectOffering?->teacher?->user?->name,
                'credits' => self::CREDITS_PER_SUBJECT,
                'currentScore' => $total !== null ? (float) $total : 0,
                'grade' => $grade,
                'progress' => $progress,
            ];
        })->values();

        $progress = [
            ['label' => 'Credits Completed', 'value' => round($enrollments->count() * self::CREDITS_PER_SUBJECT / self::TOTAL_CREDITS * 100), 'display' => ($enrollments->count() * self::CREDITS_PER_SUBJECT) . ' / ' . self::TOTAL_CREDITS, 'color' => '#2563eb', 'icon' => 'bi bi-patch-check'],
            ['label' => 'Semester Progress', 'value' => $currentTerm ? 68 : 0, 'display' => $currentTerm ? '68%' : '—', 'color' => '#0ea5e9', 'icon' => 'bi bi-bar-chart'],
            ['label' => 'Graduation Progress', 'value' => round($enrollments->count() * self::CREDITS_PER_SUBJECT / self::TOTAL_CREDITS * 100), 'display' => round($enrollments->count() * self::CREDITS_PER_SUBJECT / self::TOTAL_CREDITS * 100) . '%', 'color' => '#f97316', 'icon' => 'bi bi-mortarboard'],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'profile' => $profile,
                'summary' => $summary,
                'termTrends' => $termTrends,
                'currentSubjects' => $currentSubjects,
                'progress' => $progress,
            ],
        ]);
    }

    /**
     * Scores grouped by term and by assessment type (quiz/assignment/midterm/final).
     */
    public function scores(Request $request): JsonResponse
    {
        $student = $this->student($request);
        if (!$student) {
            return response()->json(['message' => 'No student record linked to this account.'], 403);
        }

        $student->load([
            'enrollments.subjectOffering.subject',
            'enrollments.subjectOffering.term',
            'enrollments.score.quizzes',
            'enrollments.score.assignments',
            'enrollments.score.midterms',
            'enrollments.score.finals',
        ]);

        $byTerm = $student->enrollments->groupBy(fn ($e) => $e->subjectOffering?->term?->name ?? 'Unknown')
            ->map(function ($items, $term) {
                return [
                    'term' => $term,
                    'subjects' => $items->map(function ($e) {
                        $score = $e->score;
                        return [
                            'subject' => $e->subjectOffering?->subject?->name,
                            'total' => $score?->total !== null ? (float) $score->total : null,
                            'grade' => $score?->grade,
                            'quiz' => $this->detailMark($score?->quizzes),
                            'assignment' => $this->detailMark($score?->assignments),
                            'midterm' => $this->detailMark($score?->midterms),
                            'final' => $this->detailMark($score?->finals),
                        ];
                    })->values(),
                ];
            })->values();

        return response()->json(['success' => true, 'data' => $byTerm]);
    }

    /**
     * Transcript data grouped by term.
     */
    public function transcript(Request $request): JsonResponse
    {
        $student = $this->student($request);
        if (!$student) {
            return response()->json(['message' => 'No student record linked to this account.'], 403);
        }

        $student->load([
            'user',
            'generation',
            'studentNumberSequence',
            'enrollments.subjectOffering.subject',
            'enrollments.subjectOffering.term',
            'enrollments.score.details.assessmentType',
        ]);

        $terms = $student->enrollments->groupBy(fn ($e) => $e->subjectOffering?->term?->name ?? 'Unknown')
            ->map(function ($items, $term) {
                $scored = $items->filter(fn ($e) => $e->score && $e->score->total !== null);
                $termAvg = $scored->count() ? round($scored->avg(fn ($e) => $e->score->total), 2) : 0;
                return [
                    'term' => $term,
                    'average' => $termAvg,
                    'subjects' => $items->map(function ($e) {
                        $score = $e->score;
                        $marks = $score?->details?->groupBy(fn ($d) => $d->assessmentType?->code)?->map(
                            fn ($group) => round($group->avg(fn ($d) => $d->mark ?? 0), 1)
                        ) ?? new Collection();
                        return [
                            'subject' => $e->subjectOffering?->subject?->name,
                            'total' => $score?->total !== null ? (float) $score->total : null,
                            'grade' => $score?->grade,
                            'quiz' => $marks->get('quiz'),
                            'assignment' => $marks->get('assignment'),
                            'midterm' => $marks->get('midterm'),
                            'final' => $marks->get('final'),
                        ];
                    })->values(),
                ];
            })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'student' => [
                    'name' => $student->user?->name,
                    'studentId' => $student->student_number,
                    'generation' => $student->generation?->name,
                ],
                'terms' => $terms,
            ],
        ]);
    }

    /**
     * Downloadable printable transcript (HTML, print-to-PDF ready).
     */
    public function transcriptDownload(Request $request)
    {
        $student = $this->student($request);
        if (!$student) {
            return response()->json(['message' => 'No student record linked to this account.'], 403);
        }

        $student->load([
            'user',
            'generation',
            'studentNumberSequence',
            'enrollments.subjectOffering.subject',
            'enrollments.subjectOffering.term',
            'enrollments.score.details.assessmentType',
        ]);

        $terms = $student->enrollments->groupBy(fn ($e) => $e->subjectOffering?->term?->name ?? 'Unknown')
            ->map(function ($items, $term) {
                $scored = $items->filter(fn ($e) => $e->score && $e->score->total !== null);
                $termAvg = $scored->count() ? round($scored->avg(fn ($e) => $e->score->total), 2) : 0;
                return [
                    'term' => $term,
                    'average' => $termAvg,
                    'subjects' => $items->map(function ($e) {
                        $score = $e->score;
                        $marks = $score?->details?->groupBy(fn ($d) => $d->assessmentType?->code)?->map(
                            fn ($group) => round($group->avg(fn ($d) => $d->mark ?? 0), 1)
                        ) ?? new Collection();
                        return [
                            'subject' => $e->subjectOffering?->subject?->name,
                            'total' => $score?->total !== null ? (float) $score->total : null,
                            'grade' => $score?->grade,
                            'quiz' => $marks->get('quiz'),
                            'assignment' => $marks->get('assignment'),
                            'midterm' => $marks->get('midterm'),
                            'final' => $marks->get('final'),
                        ];
                    })->values(),
                ];
            })->values();

        $html = view('transcripts.student', [
            'name' => $student->user?->name,
            'studentId' => $student->student_number,
            'generation' => $student->generation?->name,
            'terms' => $terms,
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="transcript-' . ($student->student_number ?? 'student') . '.html"',
        ]);
    }

    private function detailMark($details): ?float
    {
        if (!$details || $details->count() === 0) {
            return null;
        }
        $avg = $details->avg(fn ($d) => $d->mark ?? 0);
        return $avg !== null ? round($avg, 1) : null;
    }

    private function currentTerm($enrollments)
    {
        return $enrollments->filter(fn ($e) => $e->subjectOffering?->term)
            ->sortByDesc(fn ($e) => $e->subjectOffering->term->id)
            ->first()?->subjectOffering?->term;
    }

    private function currentClass(Student $student, $currentTerm)
    {
        $enrollment = $student->enrollments
            ->where('subjectOffering.term_id', $currentTerm?->id)
            ->first()
            ?? $student->enrollments->first();

        return $enrollment?->subjectOffering?->class?->name
            ?? $currentTerm?->name
            ?? '—';
    }

    private function termTrends($enrollments): array
    {
        return $enrollments->filter(fn ($e) => $e->subjectOffering?->term)
            ->groupBy(fn ($e) => $e->subjectOffering->term->name)
            ->map(function ($items, $term) {
                $scored = $items->filter(fn ($e) => $e->score && $e->score->total !== null);
                return [
                    'term' => $term,
                    'gpa' => $scored->count() ? round($scored->avg(fn ($e) => $this->gradePoints($e->score->grade ?? $this->gradeFromTotal($e->score->total))), 2) : 0,
                    'average' => $scored->count() ? round($scored->avg(fn ($e) => $e->score->total), 2) : 0,
                ];
            })
            ->sortBy(fn ($t) => $term = $enrollments->firstWhere(fn ($e) => ($e->subjectOffering?->term?->name ?? '') === $t['term'])?->subjectOffering?->term?->id)
            ->values()
            ->all();
    }
}
