<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
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

        // Ensure all grades A, B, C, D, F are present
        $allGrades = ['A', 'B', 'C', 'D', 'F'];
        $gradeCounts = [];
        $gradeLabels = ['A' => 'A (90-100)', 'B' => 'B (80-89)', 'C' => 'C (70-79)', 'D' => 'D (60-69)', 'F' => 'F (0-59)'];
        $gradeColors = ['A' => '#22c55e', 'B' => '#3b82f6', 'C' => '#f59e0b', 'D' => '#f97316', 'F' => '#ef4444'];

        $gradeData = $grades->keyBy('grade');

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
            ->join('subjects', 'scores.subject_id', '=', 'subjects.id')
            ->select(
                'subjects.name as subject',
                DB::raw('ROUND(AVG(scores.total), 2) as average_score'),
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

        $averageScore = DB::table('scores')->avg('total');

        return response()->json([
            'success' => true,
            'data' => [
                'total_students' => $totalStudents,
                'total_scores' => $totalScores,
                'total_subjects' => $totalSubjects,
                'total_classes' => $totalClasses,
                'average_score' => round($averageScore, 2),
            ],
        ]);
    }
}
