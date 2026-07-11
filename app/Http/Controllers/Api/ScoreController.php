<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Score;
use App\Models\ScoreDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScoreController extends Controller
{
    // GET /scores?student_id=&subject_id=&term_id=
    public function index(Request $request): JsonResponse
    {
        $query = Score::with(['details', 'student.user', 'subject', 'term']);

        if ($request->student_id) {
            $query->where('student_id', $request->student_id);
        }
        if ($request->subject_id) {
            $query->where('subject_id', $request->subject_id);
        }
        if ($request->term_id) {
            $query->where('term_id', $request->term_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    // GET /scores/{score}
    public function show(Score $score): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $score->load(['details', 'student.user', 'subject', 'term']),
        ]);
    }

    // POST /scores
    // Create a score record for student+subject+term, with initial details
    // Body: { student_id, subject_id, term_id, remarks, details: [{type, label, mark}] }
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'student_id'        => 'required|exists:students,id',
            'subject_id'        => 'required|exists:subjects,id',
            'term_id'           => 'required|exists:terms,id',
            'remarks'           => 'nullable|string',
            'details'           => 'nullable|array',
            'details.*.type'    => 'required_with:details|in:quiz,assignment,midterm,final',
            'details.*.label'   => 'required_with:details|string|max:50',
            'details.*.mark'    => 'nullable|numeric|min:0|max:100',
        ]);

        DB::beginTransaction();
        try {
            $score = Score::create([
                'student_id' => $request->student_id,
                'subject_id' => $request->subject_id,
                'term_id'    => $request->term_id,
                'remarks'    => $request->remarks,
            ]);

            if ($request->details) {
                foreach ($request->details as $detail) {
                    ScoreDetail::create([
                        'score_id' => $score->id,
                        'type'     => $detail['type'],
                        'label'    => $detail['label'],
                        'mark'     => $detail['mark'] ?? null,
                    ]);
                }
            }

            $this->recalculateTotal($score);
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $score->load('details'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // POST /scores/{score}/details — add a new quiz (or any detail) to existing score
    // Body: { type, label, mark }
    public function addDetail(Request $request, Score $score): JsonResponse
    {
        $request->validate([
            'type'  => 'required|in:quiz,assignment,midterm,final',
            'label' => 'required|string|max:50',
            'mark'  => 'nullable|numeric|min:0|max:100',
        ]);

        $detail = ScoreDetail::create([
            'score_id' => $score->id,
            'type'     => $request->type,
            'label'    => $request->label,
            'mark'     => $request->mark ?? null,
        ]);

        $this->recalculateTotal($score);

        return response()->json([
            'success' => true,
            'data' => $score->load('details'),
        ], 201);
    }

    // PUT /scores/{score}/details/{detail} — update a specific detail (e.g. enter quiz mark)
    // Body: { mark } or { label, mark }
    public function updateDetail(Request $request, Score $score, ScoreDetail $detail): JsonResponse
    {
        if ($detail->score_id !== $score->id) {
            return response()->json(['message' => 'Detail does not belong to this score.'], 403);
        }

        $request->validate([
            'label' => 'sometimes|string|max:50',
            'mark'  => 'nullable|numeric|min:0|max:100',
        ]);

        $detail->update($request->only('label', 'mark'));
        $this->recalculateTotal($score);

        return response()->json([
            'success' => true,
            'data' => $score->load('details'),
        ]);
    }

    // DELETE /scores/{score}/details/{detail} — remove a quiz
    public function deleteDetail(Score $score, ScoreDetail $detail): JsonResponse
    {
        if ($detail->score_id !== $score->id) {
            return response()->json(['message' => 'Detail does not belong to this score.'], 403);
        }

        $detail->delete();
        $this->recalculateTotal($score);

        return response()->json([
            'success' => true,
            'data' => $score->load('details'),
        ]);
    }

    // DELETE /scores/{score}
    public function destroy(Score $score): JsonResponse
    {
        $score->delete();
        return response()->json(['message' => 'Score deleted.']);
    }

    // Recalculate total using formula:
    // Quiz avg × 20% + Assignment avg × 10% + Midterm × 30% + Final × 40%
    private function recalculateTotal(Score $score): void
    {
        $details = ScoreDetail::where('score_id', $score->id)->whereNotNull('mark')->get();

        if ($details->isEmpty()) {
            $score->update(['total' => null, 'grade' => null]);
            return;
        }

        $quizAvg       = $details->where('type', 'quiz')->avg('mark');
        $assignmentAvg = $details->where('type', 'assignment')->avg('mark');
        $midterm       = $details->where('type', 'midterm')->avg('mark');
        $final         = $details->where('type', 'final')->avg('mark');

        // Only calculate total if at least one component exists
        $total = round(
            (($quizAvg ?? 0) * 0.20) +
            (($assignmentAvg ?? 0) * 0.10) +
            (($midterm ?? 0) * 0.30) +
            (($final ?? 0) * 0.40),
            2
        );

        $grade = match (true) {
            $total >= 90 => 'A',
            $total >= 80 => 'B',
            $total >= 70 => 'C',
            $total >= 60 => 'D',
            default      => 'F',
        };

        $score->update(['total' => $total, 'grade' => $grade]);
    }
}
