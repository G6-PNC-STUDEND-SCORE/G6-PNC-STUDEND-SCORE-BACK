<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    // GET /classes — all authenticated users
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = SchoolClass::with(['teacher.user', 'generation']);

        // Teacher only sees their own classes
        if ($user->hasRole('teacher')) {
            $teacher = Teacher::where('user_id', $user->id)->first();
            if ($teacher) {
                $query->where('teacher_id', $teacher->id);
            }
        }

        $classes = $query->get();

        // Hide sensitive generation data from API response
        $classes->each(function ($class) {
            if ($class->generation) {
                $class->generation->makeHidden(['year', 'is_current', 'created_at', 'updated_at']);
            }
        });

        return response()->json([
            'success' => true,
            'data'    => $classes,
        ]);
    }

    // POST /classes — admin only
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'          => 'required|string|max:100',
            'teacher_id'    => 'nullable|exists:teachers,id',
            'generation_id' => 'nullable|exists:generations,id',
            'description'   => 'nullable|string',
        ]);

        $class = SchoolClass::create($request->only('name', 'teacher_id', 'generation_id', 'description'));

        $class->load(['teacher.user', 'generation']);

        // Hide sensitive generation data from API response
        if ($class->generation) {
            $class->generation->makeHidden(['year', 'is_current', 'created_at', 'updated_at']);
        }

        return response()->json([
            'success' => true,
            'data'    => $class,
            'message' => 'Class created successfully',
        ], 201);
    }

    // PUT /classes/{class} — admin only
    public function update(Request $request, SchoolClass $class): JsonResponse
    {
        $request->validate([
            'name'          => 'sometimes|string|max:100',
            'teacher_id'    => 'nullable|exists:teachers,id',
            'generation_id' => 'nullable|exists:generations,id',
            'description'   => 'nullable|string',
            'is_active'     => 'boolean',
        ]);

        $class->update($request->only('name', 'teacher_id', 'generation_id', 'description', 'is_active'));

        $class = $class->fresh()->load(['teacher.user', 'generation']);

        // Hide sensitive generation data from API response
        if ($class->generation) {
            $class->generation->makeHidden(['year', 'is_current', 'created_at', 'updated_at']);
        }

        return response()->json([
            'success' => true,
            'data'    => $class,
            'message' => 'Class updated successfully',
        ]);
    }

    // DELETE /classes/{class} — admin only
    public function destroy(SchoolClass $class): JsonResponse
    {
        $class->delete();

        return response()->json([
            'success' => true,
            'message' => 'Class deleted successfully',
        ]);
    }
}
