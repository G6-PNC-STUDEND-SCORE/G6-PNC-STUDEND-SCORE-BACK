<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\SubjectOffering;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportCardController extends Controller
{
    /**
     * List all report cards.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ReportCard::with([
            'student.user',
            'generation',
            'term',
            'generatedBy',
        ]);

        if ($request->student_id) {
            $query->where('student_id', $request->student_id);
        }
        if ($request->term_id) {
            $query->where('term_id', $request->term_id);
        }
        if ($request->generation_id) {
            $query->where('generation_id', $request->generation_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('generated_at', 'desc')->get(),
        ]);
    }

    /**
     * Show a single report card.
     */
    public function show(ReportCard $reportCard): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $reportCard->load([
                'student.user',
                'generation',
                'term',
                'generatedBy',
            ]),
        ]);
    }

    /**
     * Generate report cards for all students in a subject offering.
     */
    public function generateByOffering(SubjectOffering $offering, Request $request): JsonResponse
    {
        $enrollments = StudentSubjectEnrollment::with(['student', 'score'])
            ->where('subject_offering_id', $offering->id)
            ->get();

        $generatedBy = $request->user()->id;
        $generatedAt = now();
        $reportCards = [];

        foreach ($enrollments as $enrollment) {
            if (!$enrollment->score || $enrollment->score->total === null) {
                continue;
            }

            $reportCard = ReportCard::updateOrCreate(
                [
                    'student_id' => $enrollment->student_id,
                    'term_id' => $offering->term_id,
                    'generation_id' => $offering->class?->generation_id,
                ],
                [
                    'total_average' => $enrollment->score->total,
                    'grade' => $enrollment->score->grade,
                    'generated_by' => $generatedBy,
                    'generated_at' => $generatedAt,
                ]
            );

            $reportCards[] = $reportCard;
        }

        return response()->json([
            'success' => true,
            'data' => $reportCards,
            'message' => count($reportCards) . ' report card(s) generated.',
        ]);
    }

    /**
     * List all transcripts (report cards grouped by student).
     */
    public function transcriptIndex(Request $request): JsonResponse
    {
        $query = ReportCard::with([
            'student.user',
            'generation',
            'term',
        ]);

        if ($request->student_id) {
            $query->where('student_id', $request->student_id);
        }

        $transcripts = $query->orderBy('student_id')
            ->orderBy('term_id')
            ->get()
            ->groupBy('student_id');

        return response()->json([
            'success' => true,
            'data' => $transcripts,
        ]);
    }

    /**
     * Generate a full transcript for a student across all terms.
     */
    public function generateTranscript(Student $student, Request $request): JsonResponse
    {
        $enrollments = StudentSubjectEnrollment::with(['score', 'subjectOffering.term'])
            ->where('student_id', $student->id)
            ->get()
            ->groupBy(fn ($e) => $e->subjectOffering?->term_id);

        $generatedBy = $request->user()->id;
        $generatedAt = now();
        $reportCards = [];

        foreach ($enrollments as $termId => $termEnrollments) {
            $scores = $termEnrollments->pluck('score')->filter(fn ($s) => $s && $s->total !== null);

            if ($scores->isEmpty()) {
                continue;
            }

            $totalAverage = round($scores->avg('total'), 2);
            $term = $termEnrollments->first()->subjectOffering?->term;
            $generation = $termEnrollments->first()->subjectOffering?->class?->generation;

            $reportCard = ReportCard::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'term_id' => $termId,
                    'generation_id' => $generation?->id,
                ],
                [
                    'total_average' => $totalAverage,
                    'grade' => \App\Models\GradeBoundary::getGrade($totalAverage) ?? 'F',
                    'generated_by' => $generatedBy,
                    'generated_at' => $generatedAt,
                ]
            );

            $reportCards[] = $reportCard;
        }

        return response()->json([
            'success' => true,
            'data' => $reportCards,
            'message' => count($reportCards) . ' term report card(s) generated for transcript.',
        ]);
    }
}