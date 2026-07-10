<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Score;
use App\Models\Student;
use App\Models\StudentNumberSequence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    // GET /students — admin & teacher see all, student sees only themselves
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasRole('student')) {
            $students = Student::with(['user', 'class', 'generation', 'studentNumberSequence'])
                ->where('user_id', $user->id)
                ->get();
        } else {
            $students = Student::with(['user', 'class', 'generation', 'studentNumberSequence'])
                ->get();
        }

        return response()->json($students);
    }

    // GET /students/{student}
    public function show(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();

        if ($user->hasRole('student') && $student->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($student->load(['user', 'class', 'generation', 'studentNumberSequence', 'scores.details', 'scores.subject', 'scores.term']));
    }

    // POST /students — admin only
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'user_id'       => 'required|exists:users,id|unique:students,user_id',
            'generation_id' => 'nullable|exists:generations,id',
            'class_id'      => 'nullable|exists:classes,id',
        ]);

        DB::beginTransaction();
        try {
            $intakeYear = now()->year;
            $nextSeq    = StudentNumberSequence::where('intake_year', $intakeYear)->count() + 1;

            $sequence = StudentNumberSequence::create([
                'intake_year'    => $intakeYear,
                'student_number' => sprintf('PNC%d-%03d', $intakeYear, $nextSeq),
            ]);

            $student = Student::create([
                'user_id'                    => $request->user_id,
                'student_number_sequence_id' => $sequence->id,
                'generation_id'              => $request->generation_id,
                'class_id'                   => $request->class_id,
            ]);

            DB::commit();
            return response()->json($student->load(['user', 'class', 'generation', 'studentNumberSequence']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // PUT /students/{student} — admin only
    public function update(Request $request, Student $student): JsonResponse
    {
        $request->validate([
            'generation_id' => 'nullable|exists:generations,id',
            'class_id'      => 'nullable|exists:classes,id',
        ]);

        $student->update($request->only('generation_id', 'class_id'));

        return response()->json($student->fresh()->load(['user', 'class', 'generation', 'studentNumberSequence']));
    }

    // DELETE /students/{student} — admin only
    public function destroy(Student $student): JsonResponse
    {
        $student->delete();
        return response()->json(['message' => 'Student deleted successfully.']);
    }

    // GET /students/{student}/scores — student sees own, teacher & admin see all
    public function scores(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();

        if ($user->hasRole('student') && $student->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $scores = Score::with(['details', 'subject', 'term'])
            ->where('student_id', $student->id)
            ->get();

        return response()->json($scores);
    }
}
