<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClassRankingResource;
use App\Http\Resources\ClassSummaryResource;
use App\Http\Resources\StudentReportResource;
use App\Models\Student;
use App\Services\ReportService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

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

    public function studentReport(int $studentId): JsonResponse
    {
        try {
            return $this->success(
                StudentReportResource::make($this->reports->studentApiReport($studentId))->resolve(),
                'Student report retrieved successfully.'
            );
        } catch (ModelNotFoundException) {
            return $this->error('Student not found.', 404);
        } catch (Throwable $exception) {
            Log::error('Failed to retrieve student report.', [
                'student_id' => $studentId,
                'exception' => $exception,
            ]);

            return $this->error('Unable to retrieve student report.', 500);
        }
    }

    public function classSummary(int $classId): JsonResponse
    {
        try {
            return $this->success(
                ClassSummaryResource::make($this->reports->classApiSummary($classId))->resolve(),
                'Class summary retrieved successfully.'
            );
        } catch (ModelNotFoundException) {
            return $this->error('Class not found.', 404);
        } catch (Throwable $exception) {
            Log::error('Failed to retrieve class summary.', [
                'class_id' => $classId,
                'exception' => $exception,
            ]);

            return $this->error('Unable to retrieve class summary.', 500);
        }
    }

    public function classRankings(int $classId): JsonResponse
    {
        try {
            return $this->success(
                ClassRankingResource::collection($this->reports->classApiRankings($classId))->resolve(),
                'Class rankings retrieved successfully.'
            );
        } catch (ModelNotFoundException) {
            return $this->error('Class not found.', 404);
        } catch (Throwable $exception) {
            Log::error('Failed to retrieve class rankings.', [
                'class_id' => $classId,
                'exception' => $exception,
            ]);

            return $this->error('Unable to retrieve class rankings.', 500);
        }
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
