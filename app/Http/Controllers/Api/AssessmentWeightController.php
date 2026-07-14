<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssessmentWeight;
use App\Models\ScoreDetail;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AssessmentWeightController extends Controller
{
    public function index(Request $request, $subjectId)
    {
        $weights = AssessmentWeight::where('subject_id', $subjectId)
            ->orderBy('sort_order')
            ->get();
        return response()->json(['success' => true, 'data' => $weights]);
    }

    public function store(Request $request, $subjectId)
    {
        $validator = Validator::make($request->all(), [
            'weights' => 'required|array|min:1',
            'weights.*.name' => 'required|string',
            'weights.*.percent' => 'required|numeric|min:0',
            'weights.*.sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 422);
        }

        $weights = $request->input('weights');
        $total = array_sum(array_map(fn($w) => (float) $w['percent'], $weights));
        if (abs($total - 100.0) > 0.0001) {
            return response()->json(['message' => 'Total percent must equal 100'], 422);
        }

        DB::beginTransaction();
        try {
            AssessmentWeight::where('subject_id', $subjectId)->delete();
            $saved = [];
            foreach ($weights as $w) {
                $saved[] = AssessmentWeight::create([
                    'subject_id' => $subjectId,
                    'name' => $w['name'],
                    'percent' => $w['percent'],
                    'sort_order' => $w['sort_order'] ?? 0,
                ]);
            }
            DB::commit();
            return response()->json(['success' => true, 'data' => $saved]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $subjectId, $id)
    {
        $w = AssessmentWeight::where('subject_id', $subjectId)->where('id', $id)->first();
        if (!$w) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $w->delete();
        return response()->json(['success' => true]);
    }

    public function computeWeightedScores(Request $request, $subjectId)
    {
        $weights = AssessmentWeight::where('subject_id', $subjectId)->orderBy('sort_order')->get();

        // Find enrollments for this subject across offerings (latest active offerings)
        $offeringIds = Subject::find($subjectId)?->offerings()->where('status', 'active')->pluck('id') ?? collect();

        $enrollments = StudentSubjectEnrollment::with(['student.user', 'student.studentNumberSequence', 'score.details.assessmentType'])
            ->whereIn('subject_offering_id', $offeringIds)
            ->get();

        // Build a normalized list of assessment columns available (label + type code)
        $columnsMap = collect();
        foreach ($enrollments as $enr) {
            if ($enr->score && $enr->score->details) {
                foreach ($enr->score->details as $d) {
                    $key = strtolower(trim($d->label));
                    if (!$columnsMap->has($key)) {
                        $columnsMap->put($key, [
                            'label' => $d->label,
                            'type' => $d->assessmentType?->code ?? null,
                            'max_score' => $d->max_score,
                        ]);
                    }
                }
            }
        }

        $results = [];
        foreach ($enrollments as $enr) {
            $studentBreakdown = [];
            $total = 0.0;
            foreach ($weights as $w) {
                $nameKey = strtolower(trim($w->name));
                // Attempt to find a matching column by label (case-insensitive)
                $col = $columnsMap->get($nameKey);
                $raw = null;
                $max = null;
                if ($col) {
                    // Find student's detail matching label
                    $detail = collect($enr->score?->details ?? [])->first(fn($d) => strtolower(trim($d->label)) === $nameKey);
                    if ($detail) {
                        $raw = $detail->mark !== null ? (float) $detail->mark : null;
                        $max = $detail->max_score !== null ? (float) $detail->max_score : null;
                    }
                } else {
                    // No direct label match - try match by assessment type
                    $detail = collect($enr->score?->details ?? [])->first(fn($d) => strtolower($d->assessmentType?->code ?? '') === strtolower($w->name));
                    if ($detail) {
                        $raw = $detail->mark !== null ? (float) $detail->mark : null;
                        $max = $detail->max_score !== null ? (float) $detail->max_score : null;
                    }
                }

                $contribution = 0.0;
                if ($raw !== null) {
                    if ($max && $max > 0) {
                        $contribution = ($raw / $max) * (float) $w->percent;
                    } else {
                        // assume raw is already percentage
                        $contribution = ($raw / 100.0) * (float) $w->percent;
                    }
                }
                $total += $contribution;
                $studentBreakdown[] = [
                    'name' => $w->name,
                    'percent' => (float) $w->percent,
                    'raw_score' => $raw,
                    'max_score' => $max,
                    'contribution' => round($contribution, 2),
                ];
            }

            $results[] = [
                'student_id' => $enr->student->id,
                'student_name' => $enr->student->user?->name ?? 'N/A',
                'weighted_total' => round($total, 2),
                'breakdown' => $studentBreakdown,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'weights' => $weights,
                'results' => $results,
            ],
        ]);
    }
}
