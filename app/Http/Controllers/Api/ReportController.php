<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Analytical reports for admins and teachers (Reports page).
 *
 * Distinct from ReportCardController, which persists report_cards/transcripts
 * rows. Nothing here writes — every endpoint is a live read over scores.
 */
class ReportController extends Controller
{
    use ApiResponds;

    public function __construct(private readonly ReportService $reports)
    {
    }

    public function filters(): JsonResponse
    {
        return $this->success($this->reports->filterOptions());
    }

    public function overview(Request $request): JsonResponse
    {
        return $this->success($this->reports->overview($this->scope($request)));
    }

    public function classPerformance(Request $request): JsonResponse
    {
        return $this->success($this->reports->classPerformance($this->scope($request)));
    }

    public function subjectRanking(Request $request): JsonResponse
    {
        return $this->success($this->reports->subjectRanking($this->scope($request)));
    }

    public function studentRanking(Request $request): JsonResponse
    {
        return $this->success($this->reports->studentRanking($this->scope($request)));
    }

    public function studentReportCard(Request $request, Student $student): JsonResponse
    {
        return $this->success($this->reports->studentReportCard($student, $this->scope($request)));
    }

    /**
     * Validated filter scope shared by every report endpoint.
     */
    private function scope(Request $request): array
    {
        return $request->validate([
            'term_id' => 'nullable|integer|exists:terms,id',
            'class_id' => 'nullable|integer|exists:classes,id',
            'subject_id' => 'nullable|integer|exists:subjects,id',
            'teacher_id' => 'nullable|integer|exists:teachers,id',
            'academic_year_id' => 'nullable|integer|exists:academic_years,id',
            'generation_id' => 'nullable|integer|exists:generations,id',
        ]);
    }
}
