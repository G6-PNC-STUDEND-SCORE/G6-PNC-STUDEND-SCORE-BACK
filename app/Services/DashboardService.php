<?php

namespace App\Services;

use App\Models\Generation;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\SchoolClass;
use App\Models\SubjectOffering;
use App\Models\StudentSubjectEnrollment;
use App\Models\Score;
use App\Models\ReportCard;
use App\Models\Transcript;
use App\Models\ActivityLog;
use App\Models\Term;
use App\Models\Department;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardService
{
    protected int $cacheTtl = 300; // 5 minutes

    /**
     * Get all dashboard data in one response.
     */
    public function getDashboardData(?array $filters = []): array
    {
        $cacheKey = $this->buildCacheKey($filters);
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($filters) {
            return $this->calculateDashboardData($filters);
        });
    }

    /**
     * Get filter dropdown options.
     */
    public function getFilterOptions(): array
    {
        return Cache::remember('dashboard_filters', 3600, function () {
            return [
                'generations' => Generation::orderBy('year', 'desc')
                    ->get(['id', 'year', 'is_current'])
                    ->map(fn (Generation $generation) => [
                        'id' => $generation->id,
                        'year' => $generation->year,
                        'name' => 'Generation '.$generation->year,
                        'is_current' => (bool) $generation->is_current,
                    ]),
                'terms'       => Term::orderBy('term_number', 'desc')->get(['id', 'name', 'term_number']),
                'classes'     => SchoolClass::with('generation:id,year')
                    ->orderBy('name')
                    ->get(['id', 'name', 'generation_id']),
                'departments' => Department::orderBy('name')->get(['id', 'name']),
                'teachers'    => Teacher::with('user:id,name')
                    ->orderBy('id')
                    ->get(['id', 'user_id']),
            ];
        });
    }

    protected function buildCacheKey(?array $filters): string
    {
        $parts = [
            $filters['generation_id'] ?? 'all',
            $filters['term_id'] ?? 'all',
            $filters['class_id'] ?? 'all',
            $filters['department_id'] ?? 'all',
            $filters['teacher_id'] ?? 'all',
        ];
        return 'dashboard_' . implode('_', $parts);
    }

    protected function calculateDashboardData(array $filters): array
    {
        $gId = $filters['generation_id'] ?? null;
        $tId = $filters['term_id'] ?? null;
        $cId = $filters['class_id'] ?? null;
        $dId = $filters['department_id'] ?? null;
        $teId = $filters['teacher_id'] ?? null;

        return [
            'kpi'    => $this->getKpiCards($gId, $tId, $cId, $dId, $teId),
            'charts' => $this->getChartData($gId, $tId, $cId, $dId, $teId),
        ];
    }

    protected function getKpiCards(?int $gId, ?int $tId, ?int $cId, ?int $dId, ?int $teId): array
    {
        $stuQ = Student::query();
        $tchQ = Teacher::query();
        $clsQ = SchoolClass::query();
        $subQ = Subject::query();
        $offQ = SubjectOffering::query();
        $enrQ = StudentSubjectEnrollment::query();
        $scrQ = Score::query();
        $rcQ = ReportCard::query();
        $trQ = Transcript::query();

        // Apply filters
        if ($gId) {
            $stuQ->where('generation_id', $gId);
            $clsQ->where('generation_id', $gId);
            $offQ->where('generation_id', $gId);
            $rcQ->where('generation_id', $gId);
            $trQ->where('generation_id', $gId);
        }
        if ($tId) {
            $offQ->where('term_id', $tId);
            $rcQ->where('term_id', $tId);
        }
        if ($cId) {
            $offQ->where('class_id', $cId);
            $clsQ->where('id', $cId);
        }
        if ($dId) {
            $tchQ->where('department_id', $dId);
            $clsQ->whereIn('teacher_id', Teacher::query()->select('id')->where('department_id', $dId));
        }
        if ($teId) {
            $tchQ->where('id', $teId);
            $clsQ->where('teacher_id', $teId);
            $offQ->where('teacher_id', $teId);
        }

        if ($dId) {
            $offQ->whereHas('teacher', fn ($q) => $q->where('department_id', $dId));
        }

        if ($gId || $tId || $cId || $dId || $teId) {
            $stuQ->whereHas('enrollments.subjectOffering', function ($q) use ($gId, $tId, $cId, $dId, $teId) {
                if ($gId) $q->where('generation_id', $gId);
                if ($tId) $q->where('term_id', $tId);
                if ($cId) $q->where('class_id', $cId);
                if ($teId) $q->where('teacher_id', $teId);
                if ($dId) $q->whereHas('teacher', fn ($teacher) => $teacher->where('department_id', $dId));
            });

            $enrQ->whereHas('subjectOffering', function ($q) use ($gId, $tId, $cId, $dId, $teId) {
                if ($gId) $q->where('generation_id', $gId);
                if ($tId) $q->where('term_id', $tId);
                if ($cId) $q->where('class_id', $cId);
                if ($teId) $q->where('teacher_id', $teId);
                if ($dId) $q->whereHas('teacher', fn ($teacher) => $teacher->where('department_id', $dId));
            });

            $scrQ->whereHas('enrollment.subjectOffering', function ($q) use ($gId, $tId, $cId, $dId, $teId) {
                if ($gId) $q->where('generation_id', $gId);
                if ($tId) $q->where('term_id', $tId);
                if ($cId) $q->where('class_id', $cId);
                if ($teId) $q->where('teacher_id', $teId);
                if ($dId) $q->whereHas('teacher', fn ($teacher) => $teacher->where('department_id', $dId));
            });
        }

        $activeStudents = (clone $stuQ)->whereIn('user_id', function ($q) {
            $q->select('id')->from('users')->where('status', 'active');
        })->count();
        $totalStudents  = (clone $stuQ)->count();
        $totalTeachers  = (clone $tchQ)->count();
        $totalClasses   = (clone $clsQ)->count();

        $totalSubjects = ($gId || $tId || $cId || $dId || $teId)
            ? (clone $offQ)->distinct('subject_id')->count('subject_id')
            : (clone $subQ)->count();

        $activeOfferings = (clone $offQ)->where('status', 'active')->count();
        $totalEnrollments = (clone $enrQ)->count();
        $totalRC = (clone $rcQ)->count();
        $totalTR = (clone $trQ)->count();
        $avgScore = round((clone $scrQ)->avg('total_weighted_score') ?? 0, 2);
        $avgGrade = $this->calcAvgGrade(clone $scrQ);
        $currentGen = Generation::where('is_current', true)->value('year');
        $currentTerm = Term::orderByDesc('term_number')->value('name');

        return [
            'total_students'          => $totalStudents,
            'active_students'         => $activeStudents,
            'total_teachers'          => $totalTeachers,
            'total_classes'           => $totalClasses,
            'total_subjects'          => $totalSubjects,
            'active_subject_offerings' => $activeOfferings,
            'total_enrollments'       => $totalEnrollments,
            'total_report_cards'      => $totalRC,
            'total_transcripts'       => $totalTR,
            'average_score'           => $avgScore,
            'average_grade'           => $avgGrade,
            'current_generation'      => $currentGen,
            'current_term'            => $currentTerm,
            'score_completion_rate'    => $totalEnrollments > 0 ? round(((clone $scrQ)->count() / $totalEnrollments) * 100, 1) : 0,
            'report_card_coverage'     => $totalStudents > 0 ? round(($totalRC / $totalStudents) * 100, 1) : 0,
        ];
    }

    protected function calcAvgGrade($q): string
    {
        $avg = (clone $q)->avg('total_weighted_score');
        if ($avg === null) return 'N/A';
        return match (true) {
            $avg >= 90 => 'A',
            $avg >= 80 => 'B',
            $avg >= 70 => 'C',
            $avg >= 60 => 'D',
            default    => 'F',
        };
    }

    protected function getChartData(?int $gId, ?int $tId, ?int $cId, ?int $dId, ?int $teId): array
    {
        $f = compact('gId', 'tId', 'cId', 'dId', 'teId');
        return [
            'student_growth'           => $this->studentGrowth($f),
            'students_by_generation'   => $this->studentsByGen($f),
            'students_by_department'   => $this->studentsByDept($f),
            'grade_distribution'       => $this->gradeDist($f),
            'subject_average_scores'   => $this->subjectAvgScores($f),
            'teacher_workload'         => $this->teacherWorkload($f),
            'assessment_type_averages' => $this->assessmentTypeAvg($f),
            'average_score_by_term'    => $this->avgScoreByTerm($f),
            'top_students'             => $this->topStudents($f),
            'lowest_performing_subjects' => $this->lowestSubjects($f),
            'recent_academic_activities' => $this->recentAcademic($f),
            'recent_user_activities'   => $this->recentUserActivity($f),
            'recent_report_cards'      => $this->recentReportCards($f),
            'recent_transcripts'       => $this->recentTranscripts($f),
        ];
    }

    protected function studentGrowth(array $f): array
    {
        try {
            $q = DB::table('students')
                ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"), DB::raw('COUNT(*) as count'))
                ->groupBy('month')->orderBy('month');
            if ($f['gId']) $q->where('generation_id', $f['gId']);
            $data = $q->get();
            $months = []; $counts = []; $rt = 0;
            foreach ($data as $r) { $rt += $r->count; $months[] = $r->month; $counts[] = $rt; }
            return ['months' => $months, 'counts' => $counts];
        } catch (\Throwable $e) {
            \Log::error('Dashboard studentGrowth error: '.$e->getMessage());
            return ['months' => [], 'counts' => []];
        }
    }

    protected function studentsByGen(array $f): array
    {
        $q = DB::table('students')->join('generations', 'students.generation_id', '=', 'generations.id')
            ->select('generations.year', DB::raw('COUNT(*) as count'))
            ->groupBy('generations.id', 'generations.year')->orderBy('generations.year');
        if ($f['gId']) $q->where('students.generation_id', $f['gId']);
        $data = $q->get();
        return [
            'labels' => $data->pluck('year')->map(fn($y) => "Gen {$y}")->toArray(),
            'counts' => $data->pluck('count')->toArray()
        ];
    }

    protected function studentsByDept(array $f): array
    {
        $q = DB::table('students')
            ->join('student_class_histories', 'students.id', '=', 'student_class_histories.student_id')
            ->join('student_subject_enrollments', 'student_class_histories.id', '=', 'student_subject_enrollments.student_class_history_id')
            ->join('subject_offerings', 'student_subject_enrollments.subject_offering_id', '=', 'subject_offerings.id')
            ->join('teachers', 'subject_offerings.teacher_id', '=', 'teachers.id')
            ->join('departments', 'teachers.department_id', '=', 'departments.id')
            ->select('departments.name', DB::raw('COUNT(DISTINCT students.id) as count'))
            ->groupBy('departments.id', 'departments.name');
        if ($f['gId']) $q->where('students.generation_id', $f['gId']);
        if ($f['tId']) $q->where('subject_offerings.term_id', $f['tId']);
        if ($f['cId']) $q->where('subject_offerings.class_id', $f['cId']);
        if ($f['dId']) $q->where('teachers.department_id', $f['dId']);
        if ($f['teId']) $q->where('subject_offerings.teacher_id', $f['teId']);
        $data = $q->get();
        $colors = ['#3b82f6','#22c55e','#f59e0b','#ef4444','#8b5cf6','#ec4899','#14b8a6','#f97316'];
        return [
            'labels' => $data->pluck('name')->toArray(),
            'counts' => $data->pluck('count')->toArray(),
            'colors' => array_slice($colors, 0, $data->count())
        ];
    }

    protected function gradeDist(array $f): array
    {
        $q = DB::table('scores')->select('grade', DB::raw('COUNT(*) as count'))->groupBy('grade')->orderBy('grade');
        if ($f['gId'] || $f['tId'] || $f['cId'] || $f['dId'] || $f['teId']) {
            $q->join('student_subject_enrollments', 'scores.student_subject_enrollment_id', '=', 'student_subject_enrollments.id')
              ->join('subject_offerings', 'student_subject_enrollments.subject_offering_id', '=', 'subject_offerings.id')
              ->leftJoin('teachers', 'subject_offerings.teacher_id', '=', 'teachers.id');
            if ($f['gId']) $q->where('subject_offerings.generation_id', $f['gId']);
            if ($f['tId']) $q->where('subject_offerings.term_id', $f['tId']);
            if ($f['cId']) $q->where('subject_offerings.class_id', $f['cId']);
            if ($f['dId']) $q->where('teachers.department_id', $f['dId']);
            if ($f['teId']) $q->where('subject_offerings.teacher_id', $f['teId']);
        }
        $data = $q->get()->keyBy('grade');
        $grades = ['A','B','C','D','F'];
        $labels = ['A'=>'A (90-100)','B'=>'B (80-89)','C'=>'C (70-79)','D'=>'D (60-69)','F'=>'F (0-59)'];
        $colors = ['A'=>'#22c55e','B'=>'#3b82f6','C'=>'#f59e0b','D'=>'#f97316','F'=>'#ef4444'];
        $result = []; $total = 0;
        foreach ($grades as $g) {
            $c = (int)($data[$g]->count ?? 0);
            $total += $c;
            $result[] = ['grade'=>$g,'label'=>$labels[$g],'count'=>$c,'color'=>$colors[$g]];
        }
        foreach ($result as $i => $r) {
            $result[$i]['percent'] = $total > 0 ? round(($r['count']/$total)*100, 1) : 0;
        }
        return ['grades' => $result, 'total' => $total];
    }

    protected function subjectAvgScores(array $f): array
    {
        $q = DB::table('scores')
            ->join('student_subject_enrollments', 'scores.student_subject_enrollment_id', '=', 'student_subject_enrollments.id')
            ->join('subject_offerings', 'student_subject_enrollments.subject_offering_id', '=', 'subject_offerings.id')
            ->leftJoin('teachers', 'subject_offerings.teacher_id', '=', 'teachers.id')
            ->join('subjects', 'subject_offerings.subject_id', '=', 'subjects.id')
            ->select('subjects.name', DB::raw('ROUND(AVG(scores.total_weighted_score),2) as average_score'), DB::raw('COUNT(*) as student_count'))
            ->groupBy('subjects.id','subjects.name')->orderByDesc('average_score');
        if ($f['gId']) $q->where('subject_offerings.generation_id', $f['gId']);
        if ($f['tId']) $q->where('subject_offerings.term_id', $f['tId']);
        if ($f['cId']) $q->where('subject_offerings.class_id', $f['cId']);
        if ($f['dId']) $q->where('teachers.department_id', $f['dId']);
        if ($f['teId']) $q->where('subject_offerings.teacher_id', $f['teId']);
        $data = $q->get();
        return [
            'subjects'       => $data->pluck('name')->toArray(),
            'scores'         => $data->pluck('average_score')->toArray(),
            'student_counts' => $data->pluck('student_count')->toArray()
        ];
    }

    protected function teacherWorkload(array $f): array
    {
        try {
            $q = DB::table('teachers')->join('users','teachers.user_id','=','users.id')
                ->leftJoin('subject_offerings', function ($join) use ($f) {
                    $join->on('teachers.id', '=', 'subject_offerings.teacher_id');
                    if ($f['gId']) $join->where('subject_offerings.generation_id', $f['gId']);
                    if ($f['tId']) $join->where('subject_offerings.term_id', $f['tId']);
                    if ($f['cId']) $join->where('subject_offerings.class_id', $f['cId']);
                })
                ->select('users.name', DB::raw('COUNT(DISTINCT subject_offerings.class_id) as class_count'), DB::raw('COUNT(DISTINCT subject_offerings.id) as offering_count'))
                ->groupBy('teachers.id','users.name')->orderByDesc('offering_count')->limit(12);
            if ($f['dId']) $q->where('teachers.department_id', $f['dId']);
            if ($f['teId']) $q->where('teachers.id', $f['teId']);
            $data = $q->get();
            return [
                'teachers'     => $data->pluck('name')->toArray(),
                'class_counts' => $data->pluck('class_count')->toArray(),
                'offering_counts' => $data->pluck('offering_count')->toArray(),
            ];
        } catch (\Throwable $e) {
            \Log::error('Dashboard teacherWorkload error: '.$e->getMessage());
            return ['teachers' => [], 'class_counts' => [], 'offering_counts' => []];
        }
    }

    protected function assessmentTypeAvg(array $f): array
    {
        $q = DB::table('score_details')
            ->join('assessment_types', 'score_details.assessment_type_id', '=', 'assessment_types.id')
            ->select(
                'assessment_types.code as type',
                DB::raw('ROUND(AVG(score_details.score),2) as average_mark'),
                DB::raw('ROUND(AVG(score_details.max_score),2) as average_max'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('assessment_types.code');
        if ($f['gId'] || $f['tId'] || $f['cId'] || $f['dId'] || $f['teId']) {
            $q->join('scores','score_details.score_id','=','scores.id')
              ->join('student_subject_enrollments','scores.student_subject_enrollment_id','=','student_subject_enrollments.id')
              ->join('subject_offerings','student_subject_enrollments.subject_offering_id','=','subject_offerings.id')
              ->leftJoin('teachers', 'subject_offerings.teacher_id', '=', 'teachers.id');
            if ($f['gId']) $q->where('subject_offerings.generation_id', $f['gId']);
            if ($f['tId']) $q->where('subject_offerings.term_id', $f['tId']);
            if ($f['cId']) $q->where('subject_offerings.class_id', $f['cId']);
            if ($f['dId']) $q->where('teachers.department_id', $f['dId']);
            if ($f['teId']) $q->where('subject_offerings.teacher_id', $f['teId']);
        }
        $data = $q->get()->keyBy('type');
        $types = ['quiz','assignment','midterm','final'];
        $labels = ['quiz'=>'Quiz','assignment'=>'Assignment','midterm'=>'Midterm','final'=>'Final'];
        $result = [];
        foreach ($types as $t) {
            $item = $data->get($t);
            $result[] = [
                'type'         => $t,
                'label'        => $labels[$t],
                'average_mark' => $item ? (float)$item->average_mark : 0,
                'average_max'  => $item ? (float)$item->average_max : 0,
                'count'        => $item ? (int)$item->count : 0,
                'percentage'   => $item && $item->average_max > 0
                    ? round(($item->average_mark / $item->average_max) * 100, 1)
                    : 0,
            ];
        }
        return $result;
    }

    protected function avgScoreByTerm(array $f): array
    {
        $q = DB::table('scores')
            ->join('student_subject_enrollments','scores.student_subject_enrollment_id','=','student_subject_enrollments.id')
            ->join('subject_offerings','student_subject_enrollments.subject_offering_id','=','subject_offerings.id')
            ->leftJoin('teachers', 'subject_offerings.teacher_id', '=', 'teachers.id')
            ->join('terms','subject_offerings.term_id','=','terms.id')
            ->select('terms.name as term_name','terms.term_number',DB::raw('ROUND(AVG(scores.total_weighted_score),2) as average_score'),DB::raw('COUNT(*) as count'))
            ->groupBy('terms.id','terms.name','terms.term_number')->orderBy('terms.term_number');
        if ($f['gId']) $q->where('subject_offerings.generation_id', $f['gId']);
        if ($f['tId']) $q->where('subject_offerings.term_id', $f['tId']);
        if ($f['cId']) $q->where('subject_offerings.class_id', $f['cId']);
        if ($f['dId']) $q->where('teachers.department_id', $f['dId']);
        if ($f['teId']) $q->where('subject_offerings.teacher_id', $f['teId']);
        $data = $q->get();
        return [
            'terms'  => $data->pluck('term_name')->toArray(),
            'scores' => $data->pluck('average_score')->toArray(),
            'counts' => $data->pluck('count')->toArray()
        ];
    }

    protected function topStudents(array $f): array
    {
        $q = DB::table('scores')
            ->join('student_subject_enrollments','scores.student_subject_enrollment_id','=','student_subject_enrollments.id')
            ->join('student_class_histories','student_subject_enrollments.student_class_history_id','=','student_class_histories.id')
            ->join('students','student_class_histories.student_id','=','students.id')
            ->join('users','students.user_id','=','users.id')
            ->select('users.name','students.id as student_id',DB::raw('ROUND(AVG(scores.total_weighted_score),2) as average_score'),DB::raw('MAX(scores.grade) as best_grade'),DB::raw('COUNT(scores.id) as score_count'))
            ->groupBy('users.name','students.id')->orderByDesc('average_score')->limit(10);
        if ($f['gId']) $q->where('students.generation_id', $f['gId']);
        if ($f['tId'] || $f['cId'] || $f['dId'] || $f['teId']) {
            $q->join('subject_offerings','student_subject_enrollments.subject_offering_id','=','subject_offerings.id')
                ->leftJoin('teachers', 'subject_offerings.teacher_id', '=', 'teachers.id');
            if ($f['tId']) $q->where('subject_offerings.term_id', $f['tId']);
            if ($f['cId']) $q->where('subject_offerings.class_id', $f['cId']);
            if ($f['dId']) $q->where('teachers.department_id', $f['dId']);
            if ($f['teId']) $q->where('subject_offerings.teacher_id', $f['teId']);
        }
        $data = $q->get();
        return $data->map(fn ($student) => [
            'student_id' => (int) $student->student_id,
            'name' => $student->name,
            'average_score' => (float) $student->average_score,
            'best_grade' => $student->best_grade,
            'score_count' => (int) $student->score_count,
        ])->toArray();
    }

    protected function lowestSubjects(array $f): array
    {
        $q = DB::table('scores')
            ->join('student_subject_enrollments','scores.student_subject_enrollment_id','=','student_subject_enrollments.id')
            ->join('subject_offerings','student_subject_enrollments.subject_offering_id','=','subject_offerings.id')
            ->leftJoin('teachers', 'subject_offerings.teacher_id', '=', 'teachers.id')
            ->join('subjects','subject_offerings.subject_id','=','subjects.id')
            ->select('subjects.name',DB::raw('ROUND(AVG(scores.total_weighted_score),2) as average_score'),DB::raw('COUNT(*) as enrollment_count'),DB::raw('SUM(CASE WHEN scores.total_weighted_score >= 60 THEN 1 ELSE 0 END) as pass_count'))
            ->groupBy('subjects.id','subjects.name')->orderBy('average_score')->limit(5);
        if ($f['gId']) $q->where('subject_offerings.generation_id', $f['gId']);
        if ($f['tId']) $q->where('subject_offerings.term_id', $f['tId']);
        if ($f['cId']) $q->where('subject_offerings.class_id', $f['cId']);
        if ($f['dId']) $q->where('teachers.department_id', $f['dId']);
        if ($f['teId']) $q->where('subject_offerings.teacher_id', $f['teId']);
        $data = $q->get();
        return $data->map(fn ($subject) => [
            'name' => $subject->name,
            'average_score' => (float) $subject->average_score,
            'enrollment_count' => (int) $subject->enrollment_count,
            'pass_rate' => $subject->enrollment_count > 0
                ? round(($subject->pass_count / $subject->enrollment_count) * 100, 1)
                : 0,
        ])->toArray();
    }

    protected function recentAcademic(array $f): array
    {
        try {
            $scores = Score::with('enrollment.student.user')
                ->when($f['gId'] || $f['tId'] || $f['cId'] || $f['dId'] || $f['teId'], function ($query) use ($f) {
                    $query->whereHas('enrollment.subjectOffering', function ($offering) use ($f) {
                        if ($f['gId']) $offering->where('generation_id', $f['gId']);
                        if ($f['tId']) $offering->where('term_id', $f['tId']);
                        if ($f['cId']) $offering->where('class_id', $f['cId']);
                        if ($f['teId']) $offering->where('teacher_id', $f['teId']);
                        if ($f['dId']) {
                            $offering->whereHas('teacher', fn ($teacher) => $teacher->where('department_id', $f['dId']));
                        }
                    });
                })
                ->orderBy('created_at','desc')->limit(10)->get();
            return $scores->map(fn($s) => [
                'id'              => $s->id,
                'action'          => 'Score Recorded',
                'student_name'    => $s->enrollment?->student?->user?->name ?? 'Unknown',
                'total'           => $s->total,
                'grade'           => $s->grade,
                'created_at'      => $s->created_at->diffForHumans(),
                'created_at_raw'  => $s->created_at->toISOString(),
            ])->toArray();
        } catch (\Throwable $e) {
            \Log::error('Dashboard recentAcademic error: '.$e->getMessage());
            return [];
        }
    }

    protected function recentUserActivity(array $f): array
    {
        try {
            $logs = ActivityLog::with('user:id,name')
                ->orderBy('created_at','desc')->limit(10)->get();
            return $logs->map(fn($l) => [
                'id'             => $l->id,
                'action'         => $l->action,
                'module'         => $l->module,
                'description'    => $l->description,
                'user_name'      => $l->user?->name ?? 'System',
                'created_at'     => $l->created_at->diffForHumans(),
                'created_at_raw' => $l->created_at->toISOString(),
            ])->toArray();
        } catch (\Throwable $e) {
            \Log::error('Dashboard recentUserActivity error: '.$e->getMessage());
            return [];
        }
    }

    protected function recentReportCards(array $f): array
    {
        try {
            $q = ReportCard::with(['student.user','generation','term','generatedBy'])
                ->orderBy('generated_at','desc')->limit(10);
            if ($f['gId']) $q->where('generation_id', $f['gId']);
            if ($f['tId']) $q->where('term_id', $f['tId']);
            if ($f['cId'] || $f['dId'] || $f['teId']) {
                $q->whereExists(function ($exists) use ($f) {
                    $exists->selectRaw('1')
                        ->from('report_card_details')
                        ->join('subject_offerings', 'report_card_details.subject_offering_id', '=', 'subject_offerings.id')
                        ->leftJoin('teachers', 'subject_offerings.teacher_id', '=', 'teachers.id')
                        ->whereColumn('report_card_details.report_card_id', 'report_cards.id');

                    if ($f['cId']) $exists->where('subject_offerings.class_id', $f['cId']);
                    if ($f['dId']) $exists->where('teachers.department_id', $f['dId']);
                    if ($f['teId']) $exists->where('subject_offerings.teacher_id', $f['teId']);
                });
            }
            return $q->get()->map(fn($rc) => [
                'id'           => $rc->id,
                'student_name' => $rc->student?->user?->name ?? 'Unknown',
                'generation'   => $rc->generation?->year,
                'term'         => $rc->term?->name,
                'average'      => $rc->total_average,
                'grade'        => $rc->grade,
                'generated_by' => $rc->generatedBy?->name ?? 'System',
                'generated_at' => $rc->generated_at?->diffForHumans(),
            ])->toArray();
        } catch (\Throwable $e) {
            \Log::error('Dashboard recentReportCards error: '.$e->getMessage());
            return [];
        }
    }

    protected function recentTranscripts(array $f): array
    {
        try {
            $q = Transcript::with(['student.user','generation','generatedBy'])
                ->orderBy('generated_at','desc')->limit(10);
            if ($f['gId']) $q->where('generation_id', $f['gId']);
            if ($f['tId']) {
                $q->whereExists(function ($exists) use ($f) {
                    $exists->selectRaw('1')
                        ->from('transcript_details')
                        ->whereColumn('transcript_details.transcript_id', 'transcripts.id')
                        ->where('transcript_details.term_id', $f['tId']);
                });
            }
            if ($f['cId'] || $f['dId'] || $f['teId']) {
                $q->whereExists(function ($exists) use ($f) {
                    $exists->selectRaw('1')
                        ->from('transcript_details')
                        ->join('report_cards', 'transcript_details.report_card_id', '=', 'report_cards.id')
                        ->join('report_card_details', 'report_cards.id', '=', 'report_card_details.report_card_id')
                        ->join('subject_offerings', 'report_card_details.subject_offering_id', '=', 'subject_offerings.id')
                        ->leftJoin('teachers', 'subject_offerings.teacher_id', '=', 'teachers.id')
                        ->whereColumn('transcript_details.transcript_id', 'transcripts.id');
                    if ($f['cId']) $exists->where('subject_offerings.class_id', $f['cId']);
                    if ($f['dId']) $exists->where('teachers.department_id', $f['dId']);
                    if ($f['teId']) $exists->where('subject_offerings.teacher_id', $f['teId']);
                });
            }
            return $q->get()->map(fn($t) => [
                'id'           => $t->id,
                'student_name' => $t->student?->user?->name ?? 'Unknown',
                'generation'   => $t->generation?->year,
                'average'      => $t->overall_average,
                'grade'        => $t->overall_grade,
                'status'       => $t->status,
                'generated_by' => $t->generatedBy?->name ?? 'System',
                'generated_at' => $t->generated_at?->diffForHumans(),
            ])->toArray();
        } catch (\Throwable $e) {
            \Log::error('Dashboard recentTranscripts error: '.$e->getMessage());
            return [];
        }
    }
}
