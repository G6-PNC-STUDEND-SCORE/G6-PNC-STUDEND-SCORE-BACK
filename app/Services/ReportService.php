<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Generation;
use App\Models\GradeBoundary;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read-only reporting layer for the Reports page (teacher/admin).
 *
 * Everything here is derived from scores.total_weighted_score — the same
 * number the score sheet computes from the assessment weights — so a report
 * never disagrees with what a teacher sees in the spreadsheet.
 *
 * Grades come from the grade_boundaries table (admin-editable), never from
 * hardcoded ranges, and "pass" means "landed on any boundary above F".
 */
class ReportService
{
    /** Enrollments a student walked away from don't belong in performance stats. */
    private const EXCLUDED_ENROLLMENT_STATUSES = ['dropped'];

    private ?float $passMark = null;

    /**
     * Score mark at/above which a subject counts as passed.
     * Read from the lowest active non-F boundary so it follows the admin's scale.
     */
    public function passMark(): float
    {
        if ($this->passMark === null) {
            $min = GradeBoundary::where('is_active', true)
                ->where('grade', '!=', 'F')
                ->min('min_percent');

            $this->passMark = $min !== null ? (float) $min : 50.0;
        }

        return $this->passMark;
    }

    public function gradeFor(float|int|null $score): ?string
    {
        return $score === null ? null : (GradeBoundary::getGrade($score) ?? 'F');
    }

    /**
     * Dropdown options for the report filter bar.
     */
    public function filterOptions(): array
    {
        return [
            'academic_years' => AcademicYear::orderByDesc('year')
                ->get(['id', 'name', 'year', 'is_current']),
            'generations' => Generation::orderByDesc('year')
                ->get(['id', 'name', 'year', 'is_current']),
            'terms' => Term::with('academicYear:id,name')
                ->orderByDesc('academic_year_id')
                ->orderBy('term_number')
                ->get(['id', 'name', 'term_number', 'academic_year_id', 'is_current']),
            'classes' => SchoolClass::orderBy('name')->get(['id', 'name', 'generation_id']),
            'subjects' => Subject::orderBy('name')->get(['id', 'name', 'subject_code']),
            'teachers' => Teacher::with('user:id,name')->get(['id', 'user_id'])
                ->map(fn (Teacher $t) => ['id' => $t->id, 'name' => $t->user?->name ?? "Teacher #{$t->id}"])
                ->sortBy('name')
                ->values(),
            'grade_boundaries' => GradeBoundary::where('is_active', true)
                ->orderByDesc('min_percent')
                ->get(['grade', 'min_percent', 'max_percent', 'label', 'color']),
            'pass_mark' => $this->passMark(),
        ];
    }

    /**
     * Base join graph: one row per graded enrollment, with every dimension the
     * reports filter or group by already attached.
     */
    private function baseQuery(array $filters): Builder
    {
        $query = DB::table('scores')
            ->join('student_subject_enrollments as enr', 'scores.student_subject_enrollment_id', '=', 'enr.id')
            ->join('subject_offerings as off', 'enr.subject_offering_id', '=', 'off.id')
            ->join('student_class_histories as hist', 'enr.student_class_history_id', '=', 'hist.id')
            ->join('students as stu', 'hist.student_id', '=', 'stu.id')
            ->join('users as stu_user', 'stu.user_id', '=', 'stu_user.id')
            ->join('subjects as subj', 'off.subject_id', '=', 'subj.id')
            ->join('classes as cls', 'off.class_id', '=', 'cls.id')
            ->join('terms as trm', 'off.term_id', '=', 'trm.id')
            ->leftJoin('teachers as tch', 'off.teacher_id', '=', 'tch.id')
            ->leftJoin('users as tch_user', 'tch.user_id', '=', 'tch_user.id')
            ->whereNotIn('enr.status', self::EXCLUDED_ENROLLMENT_STATUSES);

        if (!empty($filters['term_id'])) {
            $query->where('off.term_id', $filters['term_id']);
        }
        if (!empty($filters['class_id'])) {
            $query->where('off.class_id', $filters['class_id']);
        }
        if (!empty($filters['subject_id'])) {
            $query->where('off.subject_id', $filters['subject_id']);
        }
        if (!empty($filters['teacher_id'])) {
            $query->where('off.teacher_id', $filters['teacher_id']);
        }
        if (!empty($filters['academic_year_id'])) {
            $query->where('off.academic_year_id', $filters['academic_year_id']);
        }
        if (!empty($filters['generation_id'])) {
            $query->where('stu.generation_id', $filters['generation_id']);
        }

        return $query;
    }

    /** Same graph, restricted to enrollments that actually have a computed total. */
    private function scoredQuery(array $filters): Builder
    {
        return $this->baseQuery($filters)->whereNotNull('scores.total_weighted_score');
    }

    /**
     * Headline numbers + charts for the Overview tab.
     */
    public function overview(array $filters): array
    {
        $pass = $this->passMark();

        $totals = (clone $this->scoredQuery($filters))
            ->selectRaw('COUNT(*) as graded_count')
            ->selectRaw('COUNT(DISTINCT stu.id) as student_count')
            ->selectRaw('COUNT(DISTINCT subj.id) as subject_count')
            ->selectRaw('COUNT(DISTINCT cls.id) as class_count')
            ->selectRaw('AVG(scores.total_weighted_score) as average_score')
            ->selectRaw('MAX(scores.total_weighted_score) as highest_score')
            ->selectRaw('MIN(scores.total_weighted_score) as lowest_score')
            ->selectRaw('SUM(CASE WHEN scores.total_weighted_score >= ? THEN 1 ELSE 0 END) as pass_count', [$pass])
            ->first();

        $enrolledTotal = (clone $this->baseQuery($filters))->count();
        $graded = (int) ($totals->graded_count ?? 0);
        $passCount = (int) ($totals->pass_count ?? 0);
        $average = $totals->average_score !== null ? round((float) $totals->average_score, 2) : null;

        return [
            'kpi' => [
                'students_reported' => (int) ($totals->student_count ?? 0),
                'subjects_assessed' => (int) ($totals->subject_count ?? 0),
                'classes_covered' => (int) ($totals->class_count ?? 0),
                'graded_enrollments' => $graded,
                'total_enrollments' => $enrolledTotal,
                'grading_progress' => $enrolledTotal > 0 ? round($graded / $enrolledTotal * 100, 1) : 0.0,
                'average_score' => $average ?? 0.0,
                'average_grade' => $this->gradeFor($average) ?? 'N/A',
                'highest_score' => $totals->highest_score !== null ? round((float) $totals->highest_score, 2) : 0.0,
                'lowest_score' => $totals->lowest_score !== null ? round((float) $totals->lowest_score, 2) : 0.0,
                'pass_count' => $passCount,
                'fail_count' => $graded - $passCount,
                'pass_rate' => $graded > 0 ? round($passCount / $graded * 100, 1) : 0.0,
                'pass_mark' => $pass,
            ],
            'grade_distribution' => $this->gradeDistribution($filters),
            'score_bands' => $this->scoreBands($filters),
            'term_trend' => $this->termTrend($filters),
            'assessment_breakdown' => $this->assessmentBreakdown($filters),
            'top_students' => array_slice($this->studentRanking($filters), 0, 10),
        ];
    }

    /**
     * How many results landed on each active grade boundary.
     * Driven by the boundary table rather than scores.grade so a boundary edit
     * is reflected immediately, even on rows graded before the change.
     */
    public function gradeDistribution(array $filters): array
    {
        $boundaries = GradeBoundary::where('is_active', true)
            ->orderByDesc('min_percent')
            ->get();

        $rows = (clone $this->scoredQuery($filters))
            ->select('scores.total_weighted_score as total')
            ->pluck('total');

        $counts = [];
        foreach ($boundaries as $boundary) {
            $counts[$boundary->grade] = 0;
        }

        foreach ($rows as $total) {
            $grade = $this->gradeFor((float) $total);
            if ($grade !== null && array_key_exists($grade, $counts)) {
                $counts[$grade]++;
            }
        }

        $total = max(1, $rows->count());

        return $boundaries->map(fn (GradeBoundary $b) => [
            'grade' => $b->grade,
            'label' => $b->label,
            'color' => $b->color ?? '#94a3b8',
            'min_percent' => (float) $b->min_percent,
            'max_percent' => (float) $b->max_percent,
            'count' => $counts[$b->grade],
            'percent' => round($counts[$b->grade] / $total * 100, 1),
        ])->values()->all();
    }

    /** Fixed 10-point buckets — useful for spotting a cluster the grade bands hide. */
    private function scoreBands(array $filters): array
    {
        $bands = [
            ['label' => '0-49', 'min' => 0, 'max' => 49.999],
            ['label' => '50-59', 'min' => 50, 'max' => 59.999],
            ['label' => '60-69', 'min' => 60, 'max' => 69.999],
            ['label' => '70-79', 'min' => 70, 'max' => 79.999],
            ['label' => '80-89', 'min' => 80, 'max' => 89.999],
            ['label' => '90-100', 'min' => 90, 'max' => 100],
        ];

        $totals = (clone $this->scoredQuery($filters))->pluck('scores.total_weighted_score');

        return array_map(function (array $band) use ($totals) {
            $count = $totals->filter(fn ($t) => (float) $t >= $band['min'] && (float) $t <= $band['max'])->count();
            return ['label' => $band['label'], 'count' => $count];
        }, $bands);
    }

    /** Average per term — ignores the term filter so the trend line stays a trend. */
    private function termTrend(array $filters): array
    {
        $withoutTerm = $filters;
        unset($withoutTerm['term_id']);

        return (clone $this->scoredQuery($withoutTerm))
            ->select('trm.id', 'trm.name', 'trm.term_number')
            ->selectRaw('AVG(scores.total_weighted_score) as average_score')
            ->selectRaw('COUNT(*) as graded_count')
            ->groupBy('trm.id', 'trm.name', 'trm.term_number')
            ->orderBy('trm.term_number')
            ->get()
            ->map(fn ($row) => [
                'term_id' => (int) $row->id,
                'term' => $row->name,
                'average' => round((float) $row->average_score, 2),
                'count' => (int) $row->graded_count,
            ])->all();
    }

    /** Class average per assessment type (quiz / assignment / midterm / final). */
    private function assessmentBreakdown(array $filters): array
    {
        $enrollmentIds = (clone $this->baseQuery($filters))->select('enr.id')->pluck('id');

        if ($enrollmentIds->isEmpty()) {
            return [];
        }

        $rows = DB::table('score_details as sd')
            ->join('scores as sc', 'sd.score_id', '=', 'sc.id')
            ->join('assessment_types as at', 'sd.assessment_type_id', '=', 'at.id')
            ->whereIn('sc.student_subject_enrollment_id', $enrollmentIds)
            ->whereNotNull('sd.score')
            ->select('at.code', 'at.name', 'at.weight_percent')
            ->selectRaw('AVG(sd.score) as average_mark')
            ->selectRaw('AVG(sd.max_score) as average_max')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('at.code', 'at.name', 'at.weight_percent')
            ->get();

        return $rows->map(fn ($row) => [
            'code' => $row->code,
            'name' => $row->name,
            'weight_percent' => (float) $row->weight_percent,
            'average_mark' => round((float) $row->average_mark, 2),
            'average_max' => round((float) $row->average_max, 2),
            'percentage' => $row->average_max > 0
                ? round((float) $row->average_mark / (float) $row->average_max * 100, 1)
                : 0.0,
            'count' => (int) $row->count,
        ])->sortByDesc('weight_percent')->values()->all();
    }

    /**
     * Class performance report: one row per class in the filtered scope.
     */
    public function classPerformance(array $filters): array
    {
        $pass = $this->passMark();

        $rows = (clone $this->scoredQuery($filters))
            ->select('cls.id as class_id', 'cls.name as class_name', 'cls.room')
            ->selectRaw('COUNT(DISTINCT stu.id) as student_count')
            ->selectRaw('COUNT(DISTINCT subj.id) as subject_count')
            ->selectRaw('COUNT(*) as graded_count')
            ->selectRaw('AVG(scores.total_weighted_score) as average_score')
            ->selectRaw('MAX(scores.total_weighted_score) as highest_score')
            ->selectRaw('MIN(scores.total_weighted_score) as lowest_score')
            ->selectRaw('SUM(CASE WHEN scores.total_weighted_score >= ? THEN 1 ELSE 0 END) as pass_count', [$pass])
            ->groupBy('cls.id', 'cls.name', 'cls.room')
            ->orderByDesc('average_score')
            ->get();

        $topPerClass = $this->topStudentPerClass($filters);

        $result = [];
        foreach ($rows as $index => $row) {
            $graded = (int) $row->graded_count;
            $passCount = (int) $row->pass_count;
            $average = round((float) $row->average_score, 2);

            $result[] = [
                'rank' => $index + 1,
                'class_id' => (int) $row->class_id,
                'class_name' => $row->class_name,
                'room' => $row->room,
                'student_count' => (int) $row->student_count,
                'subject_count' => (int) $row->subject_count,
                'graded_count' => $graded,
                'average' => $average,
                'grade' => $this->gradeFor($average),
                'highest' => round((float) $row->highest_score, 2),
                'lowest' => round((float) $row->lowest_score, 2),
                'pass_count' => $passCount,
                'fail_count' => $graded - $passCount,
                'pass_rate' => $graded > 0 ? round($passCount / $graded * 100, 1) : 0.0,
                'top_student' => $topPerClass[(int) $row->class_id] ?? null,
            ];
        }

        return $result;
    }

    /** Highest-averaging student in each class, keyed by class id. */
    private function topStudentPerClass(array $filters): array
    {
        $rows = (clone $this->scoredQuery($filters))
            ->select('cls.id as class_id', 'stu_user.name as student_name')
            ->selectRaw('AVG(scores.total_weighted_score) as average_score')
            ->groupBy('cls.id', 'stu.id', 'stu_user.name')
            ->orderByDesc('average_score')
            ->get();

        $top = [];
        foreach ($rows as $row) {
            $classId = (int) $row->class_id;
            if (!isset($top[$classId])) {
                $top[$classId] = [
                    'name' => $row->student_name,
                    'average' => round((float) $row->average_score, 2),
                ];
            }
        }

        return $top;
    }

    /**
     * Subject ranking: subjects ordered by class average, best first.
     */
    public function subjectRanking(array $filters): array
    {
        $pass = $this->passMark();

        $rows = (clone $this->scoredQuery($filters))
            ->select('subj.id as subject_id', 'subj.name as subject_name', 'subj.subject_code', 'subj.credits')
            ->selectRaw('COUNT(DISTINCT stu.id) as student_count')
            ->selectRaw('COUNT(DISTINCT cls.id) as class_count')
            ->selectRaw("GROUP_CONCAT(DISTINCT tch_user.name) as teacher_names")
            ->selectRaw('COUNT(*) as graded_count')
            ->selectRaw('AVG(scores.total_weighted_score) as average_score')
            ->selectRaw('MAX(scores.total_weighted_score) as highest_score')
            ->selectRaw('MIN(scores.total_weighted_score) as lowest_score')
            ->selectRaw('SUM(CASE WHEN scores.total_weighted_score >= ? THEN 1 ELSE 0 END) as pass_count', [$pass])
            ->groupBy('subj.id', 'subj.name', 'subj.subject_code', 'subj.credits')
            ->orderByDesc('average_score')
            ->get();

        $result = [];
        foreach ($rows as $index => $row) {
            $graded = (int) $row->graded_count;
            $passCount = (int) $row->pass_count;
            $average = round((float) $row->average_score, 2);

            $result[] = [
                'rank' => $index + 1,
                'subject_id' => (int) $row->subject_id,
                'subject_name' => $row->subject_name,
                'subject_code' => $row->subject_code,
                'credits' => $row->credits !== null ? (int) $row->credits : null,
                'teachers' => $row->teacher_names ? explode(',', $row->teacher_names) : [],
                'student_count' => (int) $row->student_count,
                'class_count' => (int) $row->class_count,
                'graded_count' => $graded,
                'average' => $average,
                'grade' => $this->gradeFor($average),
                'highest' => round((float) $row->highest_score, 2),
                'lowest' => round((float) $row->lowest_score, 2),
                'pass_count' => $passCount,
                'fail_count' => $graded - $passCount,
                'pass_rate' => $graded > 0 ? round($passCount / $graded * 100, 1) : 0.0,
            ];
        }

        return $result;
    }

    /**
     * Student ranking / class performance roster.
     *
     * total  = sum of every subject's weighted total
     * average = that sum over the number of graded subjects
     * result  = PASS only when the average clears the pass mark AND no single
     *           subject was failed — the rule most report cards use.
     */
    public function studentRanking(array $filters): array
    {
        $pass = $this->passMark();

        $rows = (clone $this->scoredQuery($filters))
            ->select(
                'stu.id as student_id',
                'stu.student_id_number',
                'stu_user.name as student_name',
                'stu_user.email',
                'cls.id as class_id',
                'cls.name as class_name',
            )
            ->selectRaw('COUNT(*) as subject_count')
            ->selectRaw('SUM(scores.total_weighted_score) as total_score')
            ->selectRaw('AVG(scores.total_weighted_score) as average_score')
            ->selectRaw('MAX(scores.total_weighted_score) as highest_score')
            ->selectRaw('MIN(scores.total_weighted_score) as lowest_score')
            ->selectRaw('SUM(CASE WHEN scores.total_weighted_score < ? THEN 1 ELSE 0 END) as failed_subjects', [$pass])
            ->groupBy('stu.id', 'stu.student_id_number', 'stu_user.name', 'stu_user.email', 'cls.id', 'cls.name')
            ->orderByDesc('average_score')
            ->get();

        $result = [];
        foreach ($rows as $index => $row) {
            $average = round((float) $row->average_score, 2);
            $failed = (int) $row->failed_subjects;

            $result[] = [
                'rank' => $index + 1,
                'student_id' => (int) $row->student_id,
                'student_number' => $row->student_id_number,
                'student_name' => $row->student_name,
                'email' => $row->email,
                'class_id' => (int) $row->class_id,
                'class_name' => $row->class_name,
                'subject_count' => (int) $row->subject_count,
                'total' => round((float) $row->total_score, 2),
                'average' => $average,
                'grade' => $this->gradeFor($average),
                'highest' => round((float) $row->highest_score, 2),
                'lowest' => round((float) $row->lowest_score, 2),
                'failed_subjects' => $failed,
                'result' => ($average >= $pass && $failed === 0) ? 'pass' : 'fail',
            ];
        }

        return $result;
    }

    /**
     * One student's report card for the filtered scope (normally a single term).
     */
    public function studentReportCard(Student $student, array $filters): array
    {
        $pass = $this->passMark();

        $rows = $this->baseQuery($filters)
            ->where('stu.id', $student->id)
            ->select(
                'scores.id as score_id',
                'scores.total_weighted_score as total',
                'scores.remarks',
                'enr.id as enrollment_id',
                'subj.id as subject_id',
                'subj.name as subject_name',
                'subj.subject_code',
                'subj.credits',
                'cls.id as class_id',
                'cls.name as class_name',
                'trm.id as term_id',
                'trm.name as term_name',
                'trm.term_number',
                'tch_user.name as teacher_name',
            )
            ->orderBy('trm.term_number')
            ->orderBy('subj.name')
            ->get();

        $marks = $this->assessmentMarks($rows->pluck('score_id')->all());

        $subjects = $rows->map(function ($row) use ($marks, $pass) {
            $total = $row->total !== null ? round((float) $row->total, 2) : null;

            return [
                'subject_id' => (int) $row->subject_id,
                'subject_name' => $row->subject_name,
                'subject_code' => $row->subject_code,
                'credits' => $row->credits !== null ? (int) $row->credits : null,
                'teacher' => $row->teacher_name,
                'class_name' => $row->class_name,
                'term_id' => (int) $row->term_id,
                'term_name' => $row->term_name,
                'assessments' => $marks[(int) $row->score_id] ?? [],
                'total' => $total,
                'grade' => $this->gradeFor($total),
                'result' => $total === null ? null : ($total >= $pass ? 'pass' : 'fail'),
                'remarks' => $row->remarks,
            ];
        })->values();

        $graded = $subjects->filter(fn ($s) => $s['total'] !== null);
        $totalScore = round((float) $graded->sum('total'), 2);
        $average = $graded->count() ? round($graded->avg('total'), 2) : null;
        $failed = $graded->filter(fn ($s) => $s['result'] === 'fail')->count();

        $classId = (int) ($rows->first()->class_id ?? 0);
        $rankInfo = $this->rankWithin($filters, $classId, $student->id);

        $student->loadMissing(['user', 'generation', 'classHistories.class']);

        return [
            'student' => [
                'id' => $student->id,
                'name' => $student->user?->name,
                'student_number' => $student->student_id_number,
                'email' => $student->user?->email,
                'avatar' => $student->user?->avatar,
                'gender' => $student->gender,
                'status' => $student->status,
                'generation' => $student->generation?->name ?? ($student->generation?->year ? "Generation {$student->generation->year}" : null),
                'class_name' => $rows->first()->class_name ?? $student->class?->name,
            ],
            'scope' => [
                'terms' => $subjects->pluck('term_name')->unique()->values()->all(),
                'pass_mark' => $pass,
            ],
            'subjects' => $subjects->all(),
            'summary' => [
                'subject_count' => $subjects->count(),
                'graded_count' => $graded->count(),
                'total_score' => $totalScore,
                'max_possible' => $graded->count() * 100,
                'average' => $average ?? 0.0,
                'grade' => $this->gradeFor($average) ?? 'N/A',
                'highest_subject' => $graded->sortByDesc('total')->first()['subject_name'] ?? null,
                'lowest_subject' => $graded->sortBy('total')->first()['subject_name'] ?? null,
                'failed_subjects' => $failed,
                'result' => ($average !== null && $average >= $pass && $failed === 0) ? 'pass' : 'fail',
                'rank' => $rankInfo['rank'],
                'class_size' => $rankInfo['class_size'],
                'class_average' => $rankInfo['class_average'],
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Per-assessment marks for a set of scores, keyed by score id.
     */
    private function assessmentMarks(array $scoreIds): array
    {
        if (empty($scoreIds)) {
            return [];
        }

        $details = DB::table('score_details as sd')
            ->join('assessment_types as at', 'sd.assessment_type_id', '=', 'at.id')
            ->whereIn('sd.score_id', $scoreIds)
            ->select('sd.score_id', 'sd.label', 'sd.score', 'sd.max_score', 'sd.sequence_number', 'at.code', 'at.name', 'at.weight_percent')
            ->orderBy('at.weight_percent', 'desc')
            ->orderBy('sd.sequence_number')
            ->get();

        $byScore = [];
        foreach ($details->groupBy('score_id') as $scoreId => $group) {
            $byScore[(int) $scoreId] = $group->groupBy('code')->map(function ($items, $code) {
                $first = $items->first();
                $marked = $items->filter(fn ($i) => $i->score !== null);

                return [
                    'code' => $code,
                    'name' => $first->name,
                    'weight_percent' => (float) $first->weight_percent,
                    'average' => $marked->count() ? round($marked->avg(fn ($i) => (float) $i->score), 2) : null,
                    'max_score' => $first->max_score !== null ? (float) $first->max_score : null,
                    'items' => $items->map(fn ($i) => [
                        'label' => $i->label,
                        'score' => $i->score !== null ? (float) $i->score : null,
                        'max_score' => $i->max_score !== null ? (float) $i->max_score : null,
                    ])->values()->all(),
                ];
            })->values()->all();
        }

        return $byScore;
    }

    /**
     * Where this student sits among classmates in the same filtered scope.
     */
    private function rankWithin(array $filters, int $classId, int $studentId): array
    {
        if ($classId === 0) {
            return ['rank' => null, 'class_size' => 0, 'class_average' => null];
        }

        $scoped = $filters;
        unset($scoped['student_id']);
        $scoped['class_id'] = $classId;

        $ranking = $this->studentRanking($scoped);
        $me = collect($ranking)->firstWhere('student_id', $studentId);
        $averages = collect($ranking)->pluck('average');

        return [
            'rank' => $me['rank'] ?? null,
            'class_size' => count($ranking),
            'class_average' => $averages->count() ? round($averages->avg(), 2) : null,
        ];
    }
}
