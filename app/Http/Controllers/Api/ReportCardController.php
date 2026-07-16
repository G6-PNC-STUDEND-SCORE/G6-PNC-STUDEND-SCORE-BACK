<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GradeBoundary;
use App\Models\ReportCard;
use App\Models\ReportCardDetail;
use App\Models\Score;
use App\Models\ScoreDetail;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\SubjectOffering;
use App\Models\Transcript;
use App\Models\TranscriptDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportCardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ReportCard::with(['student.user', 'generation', 'term', 'generatedBy']);

        if ($request->generation_id) {
            $query->where('generation_id', $request->generation_id);
        }
        if ($request->term_id) {
            $query->where('term_id', $request->term_id);
        }
        if ($request->student_id) {
            $query->where('student_id', $request->student_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('generated_at', 'desc')->get(),
        ]);
    }

    public function show(ReportCard $reportCard): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $reportCard->load([
                'student.user',
                'student.enrollments.subjectOffering.subject',
                'generation',
                'term',
                'generatedBy',
            ]),
        ]);
    }

    public function generateByOffering(Request $request, SubjectOffering $offering): JsonResponse
    {
        $request->validate([
            'term_id' => 'required|exists:terms,id',
        ]);

        $termId = $request->term_id;
        $generationId = $offering->generation_id;
        $enrollments = StudentSubjectEnrollment::with(['student', 'score.details.assessmentType'])
            ->where('subject_offering_id', $offering->id)
            ->where('status', 'active')
            ->get();

        if ($enrollments->isEmpty()) {
            return response()->json(['message' => 'No active enrollments found.'], 404);
        }

        $count = 0;
        DB::beginTransaction();
        try {
            foreach ($enrollments as $enrollment) {
                $score = $enrollment->score;

                if (!$score || $score->total === null) continue;

                $reportCard = ReportCard::updateOrCreate(
                    [
                        'student_id' => $enrollment->student?->id,
                        'generation_id' => $generationId,
                        'term_id' => $termId,
                    ],
                    [
                        'total_average' => $score->total,
                        'grade' => $score->grade,
                        'remarks' => $score->remarks,
                        'generated_by' => $request->user()->id,
                        'generated_at' => now(),
                    ]
                );

                $subjectAverage = $score->total;
                $subjectGrade = $score->grade;

                ReportCardDetail::updateOrCreate(
                    [
                        'report_card_id' => $reportCard->id,
                        'subject_offering_id' => $offering->id,
                    ],
                    [
                        'score_id' => $score->id,
                        'subject_average' => $subjectAverage,
                        'grade' => $subjectGrade,
                        'remarks' => $score->remarks,
                    ]
                );

                $count++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Report cards generated for {$count} students.",
        ]);
    }

    public function transcriptIndex(Request $request): JsonResponse
    {
        $query = Transcript::with(['student.user', 'generation', 'generatedBy']);

        if ($request->generation_id) {
            $query->where('generation_id', $request->generation_id);
        }
        if ($request->student_id) {
            $query->where('student_id', $request->student_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('generated_at', 'desc')->get(),
        ]);
    }

    public function generateTranscript(Request $request, Student $student): JsonResponse
    {
        $request->validate([
            'generation_id' => 'required|exists:generations,id',
        ]);

        $generationId = $request->generation_id;

        $reportCards = ReportCard::where('student_id', $student->id)
            ->where('generation_id', $generationId)
            ->get();

        if ($reportCards->isEmpty()) {
            return response()->json(['message' => 'No report cards found for this student and generation.'], 404);
        }

        $overallAverage = round($reportCards->avg('total_average'), 2);
        $overallGrade = GradeBoundary::getGrade($overallAverage) ?? 'F';

        $existing = Transcript::where('student_id', $student->id)
            ->where('generation_id', $generationId)
            ->first();

        DB::beginTransaction();
        try {
            if ($existing) {
                $existing->update([
                    'overall_average' => $overallAverage,
                    'overall_grade' => $overallGrade,
                    'generated_by' => $request->user()->id,
                    'generated_at' => now(),
                    'status' => 'generated',
                ]);
            } else {
                $transcript = Transcript::create([
                    'student_id' => $student->id,
                    'generation_id' => $generationId,
                    'overall_average' => $overallAverage,
                    'overall_grade' => $overallGrade,
                    'generated_by' => $request->user()->id,
                    'generated_at' => now(),
                    'status' => 'generated',
                ]);

                foreach ($reportCards as $rc) {
                    TranscriptDetail::create([
                        'transcript_id' => $transcript->id,
                        'report_card_id' => $rc->id,
                        'term_id' => $rc->term_id,
                        'total_average' => $rc->total_average,
                        'grade' => $rc->grade,
                    ]);
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Transcript generated successfully.',
        ]);
    }
}
