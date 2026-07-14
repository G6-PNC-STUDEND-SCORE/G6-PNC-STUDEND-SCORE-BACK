<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChartController extends Controller
{
    public function gradeDistribution(): JsonResponse
    {
        $grades = DB::table('scores')
            ->select('grade', DB::raw('COUNT(*) as count'))
            ->groupBy('grade')
            ->orderBy('grade')
            ->get();

        $allGrades = ['A', 'B', 'C', 'D', 'F'];
        $gradeLabels = ['A' => 'A (90-100)', 'B' => 'B (80-89)', 'C' => 'C (70-79)', 'D' => 'D (60-69)', 'F' => 'F (0-59)'];
        $gradeColors = ['A' => '#22c55e', 'B' => '#3b82f6', 'C' => '#f59e0b', 'D' => '#f97316', 'F' => '#ef4444'];

        $gradeData = $grades->keyBy('grade');

        $gradeCounts = [];
        foreach ($allGrades as $grade) {
            $gradeCounts[] = [
                'grade' => $grade,
                'label' => $gradeLabels[$grade],
                'count' => (int) ($gradeData[$grade]->count ?? 0),
                'color' => $gradeColors[$grade],
            ];
        }

        $total = array_sum(array_column($gradeCounts, 'count'));

        return response()->json([
            'success' => true,
            'data' => [
                'grades' => $gradeCounts,
                'total' => $total,
            ],
        ]);
    }

    public function subjectPerformance(): JsonResponse
    {
        $subjects = DB::table('scores')
            ->join('student_subject_enrollments', 'scores.student_subject_enrollment_id', '=', 'student_subject_enrollments.id')
            ->join('subject_offerings', 'student_subject_enrollments.subject_offering_id', '=', 'subject_offerings.id')
            ->join('subjects', 'subject_offerings.subject_id', '=', 'subjects.id')
            ->select(
                'subjects.name as subject',
                DB::raw('ROUND(AVG(scores.total_weighted_score), 2) as average_score'),
                DB::raw('COUNT(*) as student_count')
            )
            ->groupBy('subjects.id', 'subjects.name')
            ->orderByDesc('average_score')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $subjects,
        ]);
    }

    public function summary(): JsonResponse
    {
        $totalStudents = DB::table('students')->count();
        $totalScores = DB::table('scores')->count();
        $totalSubjects = DB::table('subjects')->count();
        $totalClasses = DB::table('classes')->count();
        $totalTeachers = DB::table('teachers')->count();

        $averageScore = DB::table('scores')->avg('total_weighted_score');

        $passCount = DB::table('scores')->where('total_weighted_score', '>=', 60)->count();
        $failCount = DB::table('scores')->where('total_weighted_score', '<', 60)->count();
        $totalWithScores = $passCount + $failCount;
        $passRate = $totalWithScores > 0 ? round(($passCount / $totalWithScores) * 100, 1) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_students' => $totalStudents,
                'total_scores' => $totalScores,
                'total_subjects' => $totalSubjects,
                'total_classes' => $totalClasses,
                'total_teachers' => $totalTeachers,
                'average_score' => round($averageScore, 2),
                'pass_count' => $passCount,
                'fail_count' => $failCount,
                'pass_rate' => $passRate,
            ],
        ]);
    }

    public function trends(Request $request): JsonResponse
    {
        $year = $request->integer('year', now()->year);

        $monthlyStats = DB::table('scores')
            ->select(
                DB::raw('MONTH(created_at) as month'),
                DB::raw('COUNT(*) as count'),
                DB::raw('ROUND(AVG(total_weighted_score), 2) as avg_score'),
                DB::raw('SUM(CASE WHEN total_weighted_score >= 60 THEN 1 ELSE 0 END) as pass_count'),
                DB::raw('SUM(CASE WHEN total_weighted_score < 60 THEN 1 ELSE 0 END) as fail_count')
            )
            ->whereYear('created_at', $year)
            ->whereNotNull('total_weighted_score')
            ->groupBy(DB::raw('MONTH(created_at)'))
            ->orderBy(DB::raw('MONTH(created_at)'))
            ->get()
            ->keyBy('month');

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $stat = $monthlyStats->get($m);
            $count = $stat->count ?? 0;
            $passCount = $stat->pass_count ?? 0;
            $months[] = [
                'month' => $m,
                'month_name' => date('M', mktime(0, 0, 0, $m, 1)),
                'avg_score' => $stat ? (float) $stat->avg_score : 0,
                'pass_rate' => $count > 0 ? round(($passCount / $count) * 100, 1) : 0,
                'count' => $count,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'year' => $year,
                'months' => $months,
            ],
        ]);
    }

    public function recentActivity(): JsonResponse
    {
        $logs = ActivityLog::with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'module' => $log->module,
                'description' => $log->description,
                'user_name' => $log->user?->name ?? 'System',
                'created_at' => $log->created_at->diffForHumans(),
                'created_at_raw' => $log->created_at->toISOString(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }
}

