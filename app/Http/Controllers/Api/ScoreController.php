<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssessmentType;
use App\Models\Score;
use App\Models\ScoreDetail;
use App\Models\StudentSubjectEnrollment;
use App\Services\ScoreCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScoreController extends Controller
{
    public function __construct(
        private readonly ScoreCalculationService $scoreService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Score::with(['details.assessmentType', 'enrollment.student.user', 'enrollment.subjectOffering.subject', 'enrollment.subjectOffering.term']);

        if ($request->student_id) {
            $query->whereHas('enrollment', function ($q) use ($request) {
                $q->where('student_id', $request->student_id);
            });
        }
        if ($request->subject_offering_id) {
            $query->whereHas('enrollment', function ($q) use ($request) {
                $q->where('subject_offering_id', $request->subject_offering_id);
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function show(Score $score): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $score->load(['details.assessmentType', 'enrollment.student.user', 'enrollment.subjectOffering.subject', 'enrollment.subjectOffering.term']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'student_subject_enrollment_id' => 'required|exists:student_subject_enrollments,id|unique:scores,student_subject_enrollment_id',
            'remarks'           => 'nullable|string',
            'details'           => 'nullable|array',
            'details.*.type'    => 'required_with:details|in:quiz,assignment,midterm,final',
            'details.*.label'   => 'required_with:details|string|max:50',
            'details.*.mark'    => 'nullable|numeric|min:0',
            'details.*.max_score' => 'nullable|integer|min:1',
            'details.*.order_number' => 'nullable|integer',
        ]);

        DB::beginTransaction();
        try {
            $score = Score::create([
                'student_subject_enrollment_id' => $request->student_subject_enrollment_id,
                'remarks'       => $request->remarks,
            ]);

            if ($request->details) {
                foreach ($request->details as $detail) {
                    ScoreDetail::create([
                        'score_id'      => $score->id,
                        'assessment_type_id' => $this->assessmentTypeId($detail['type']),
                        'label'         => $detail['label'],
                        'mark'          => $detail['mark'] ?? null,
                        'max_score'     => $detail['max_score'] ?? null,
                        'order_number'  => $detail['order_number'] ?? null,
                    ]);
                }
            }

            $this->scoreService->recalculateTotal($score);
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $score->load(['details.assessmentType', 'enrollment.subjectOffering.subject', 'enrollment.subjectOffering.term']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function addDetail(Request $request, Score $score): JsonResponse
    {
        $request->validate([
            'type'        => 'required|in:quiz,assignment,midterm,final',
            'label'       => 'required|string|max:50',
            'mark'        => 'nullable|numeric|min:0',
            'max_score'   => 'nullable|integer|min:1',
            'order_number'=> 'nullable|integer',
        ]);

        $detail = ScoreDetail::create([
            'score_id'      => $score->id,
            'assessment_type_id' => $this->assessmentTypeId($request->type),
            'label'         => $request->label,
            'mark'          => $request->mark ?? null,
            'max_score'     => $request->max_score ?? null,
            'order_number'  => $request->order_number ?? null,
        ]);

        $this->scoreService->recalculateTotal($score);

        return response()->json([
            'success' => true,
            'data' => $score->load(['details.assessmentType', 'enrollment.subjectOffering.subject', 'enrollment.subjectOffering.term']),
        ], 201);
    }

    public function updateDetail(Request $request, Score $score, ScoreDetail $detail): JsonResponse
    {
        if ($detail->score_id !== $score->id) {
            return response()->json(['message' => 'Detail does not belong to this score.'], 403);
        }

        $request->validate([
            'label'       => 'sometimes|string|max:50',
            'mark'        => 'nullable|numeric|min:0',
            'max_score'   => 'nullable|integer|min:1',
            'order_number'=> 'nullable|integer',
        ]);

        $detail->update($request->only('label', 'mark', 'max_score', 'order_number'));
        $this->scoreService->recalculateTotal($score);

        return response()->json([
            'success' => true,
            'data' => $score->load(['details.assessmentType', 'enrollment.subjectOffering.subject', 'enrollment.subjectOffering.term']),
        ]);
    }

    public function deleteDetail(Score $score, ScoreDetail $detail): JsonResponse
    {
        if ($detail->score_id !== $score->id) {
            return response()->json(['message' => 'Detail does not belong to this score.'], 403);
        }

        $detail->delete();
        $this->scoreService->recalculateTotal($score);

        return response()->json([
            'success' => true,
            'data' => $score->load(['details.assessmentType', 'enrollment.subjectOffering.subject', 'enrollment.subjectOffering.term']),
        ]);
    }

    public function destroy(Score $score): JsonResponse
    {
        $score->delete();
        return response()->json(['message' => 'Score deleted.']);
    }

    public function byEnrollment(StudentSubjectEnrollment $enrollment): JsonResponse
    {
        $score = Score::with(['details.assessmentType'])
            ->where('student_subject_enrollment_id', $enrollment->id)
            ->first();

        return response()->json([
            'success' => true,
            'data' => $score,
        ]);
    }

    private function assessmentTypeId(string $code): int
    {
        return AssessmentType::firstOrCreate(
            ['code' => $code],
            [
                'name' => ucfirst($code),
                'weight_percent' => match ($code) {
                    'quiz' => 20,
                    'assignment' => 10,
                    'midterm' => 30,
                    'final' => 40,
                    default => 0,
                },
                'is_active' => true,
            ]
        )->id;
    }
}
