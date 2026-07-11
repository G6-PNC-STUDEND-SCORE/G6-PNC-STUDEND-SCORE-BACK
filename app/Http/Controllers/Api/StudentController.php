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

        return response()->json([
            'students' => $students,
        ]);
    }

    // GET /students/{student}
    public function show(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();

        if ($user->hasRole('student') && $student->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'student' => $student->load(['user', 'class', 'generation', 'studentNumberSequence', 'scores.details', 'scores.subject', 'scores.term']),
        ]);
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

            // Update the user's name, gender if provided
            $user = \App\Models\User::find($request->user_id);
            if ($user) {
                $userData = [];
                if ($request->filled('name')) {
                    $userData['name'] = $request->name;
                }
                if ($request->filled('gender')) {
                    $userData['gender'] = $request->gender;
                }
                if (!empty($userData)) {
                    $user->update($userData);
                }
            }

            $student = Student::create([
                'user_id'                    => $request->user_id,
                'student_number_sequence_id' => $sequence->id,
                'generation_id'              => $request->generation_id,
                'class_id'                   => $request->class_id,
            ]);

            DB::commit();
            return response()->json([
                'student' => $student->load(['user', 'class', 'generation', 'studentNumberSequence']),
            ], 201);
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
            'name'          => 'nullable|string|max:255',
            'gender'        => 'nullable|in:Male,Female,Other',
            'status'        => 'nullable|in:active,inactive,suspended',
        ]);

        $student->update($request->only('generation_id', 'class_id'));

        // Update the user's name, gender, status if provided
        $userData = [];
        if ($request->filled('name')) {
            $userData['name'] = $request->name;
        }
        if ($request->filled('gender')) {
            $userData['gender'] = $request->gender;
        }
        if ($request->filled('status')) {
            $userData['status'] = $request->status;
        }
        if (!empty($userData)) {
            $student->user()->update($userData);
        }

        return response()->json([
            'student' => $student->fresh()->load(['user', 'class', 'generation', 'studentNumberSequence']),
        ]);
    }

    // DELETE /students/{student} — admin only
    public function destroy(Student $student): JsonResponse
    {
        $student->delete();
        return response()->json(['message' => 'Student deleted successfully.']);
    }

    // PUT /students/{student}/assign-class — assign a class to a student
    public function assignClass(Request $request, Student $student): JsonResponse
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
        ]);

        $student->update([
            'class_id' => $request->class_id,
        ]);

        return response()->json([
            'student' => $student->fresh()->load(['user', 'class', 'generation', 'studentNumberSequence']),
        ]);
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
