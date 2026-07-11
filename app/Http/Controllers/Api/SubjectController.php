<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    // GET /subjects — all authenticated users
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = Subject::with(['teacher.user', 'class']);

        // Teacher only sees their own subjects
        if ($user->hasRole('teacher')) {
            $teacher = Teacher::where('user_id', $user->id)->first();
            if ($teacher) {
                $query->where('teacher_id', $teacher->id);
            }
        }

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->get(),
        ]);
    }

    // GET /subjects/{subject} — all authenticated users
    public function show(Subject $subject): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $subject->load(['teacher.user', 'class']),
        ]);
    }

    // POST /subjects — admin only
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'teacher_id' => 'nullable|exists:teachers,id',
            'class_id'   => 'nullable|exists:classes,id',
            'status'     => 'in:Active,Inactive',
        ]);

        $subject = Subject::create([
            'name'       => $request->name,
            'teacher_id' => $request->teacher_id,
            'class_id'   => $request->class_id,
            'status'     => $request->status ?? 'Active',
        ]);

        return response()->json([
            'success' => true,
            'data'    => $subject->load(['teacher.user', 'class']),
            'message' => 'Subject created successfully',
        ], 201);
    }

    // PUT /subjects/{subject} — admin only
    public function update(Request $request, Subject $subject): JsonResponse
    {
        $request->validate([
            'name'       => 'sometimes|string|max:255',
            'teacher_id' => 'nullable|exists:teachers,id',
            'class_id'   => 'nullable|exists:classes,id',
            'status'     => 'in:Active,Inactive',
        ]);

        $subject->update($request->only('name', 'teacher_id', 'class_id', 'status'));

        return response()->json([
            'success' => true,
            'data'    => $subject->fresh()->load(['teacher.user', 'class']),
            'message' => 'Subject updated successfully',
        ]);
    }

    // DELETE /subjects/{subject} — admin only
    public function destroy(Subject $subject): JsonResponse
    {
        $subject->delete();
        return response()->json([
            'success' => true,
            'message' => 'Subject deleted successfully',
        ]);
    }

    // GET /teachers — admin & teacher
    public function teachers(): JsonResponse
    {
        $teachers = Teacher::with('user', 'department')->get()
            ->map(fn($t) => [
                'id'   => $t->id,
                'name' => $t->user->name,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $teachers,
        ]);
    }
}
