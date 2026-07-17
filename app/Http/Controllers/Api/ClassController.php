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

        $query->orderByDesc('id');

        return response()->json([
            'success' => true,
            'data'    => $query->get()->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'teacher_id' => $c->teacher_id,
                'academic_year_id' => $c->academic_year_id,
                'description' => $c->description,
                'is_active' => (bool) $c->is_active,
                'room' => $c->room,
                'created_at' => $c->created_at,
                'updated_at' => $c->updated_at,
                'teacher' => $c->teacher ? ['id' => $c->teacher->id, 'name' => $c->teacher->user?->name ?? null] : null,
                'academicYear' => $c->generation ? ['id' => $c->generation->id, 'name' => $c->generation->name] : null,
            ]),
        ]);
    }

    // POST /classes — admin only
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'          => 'required|string|max:100',
            'teacher_id'    => 'nullable|exists:teachers,id',
            'generation_id' => 'nullable|exists:generations,id',
            'room'          => 'nullable|string|max:50',
            'description'   => 'nullable|string',
        ]);

        $class = SchoolClass::create($request->only('name', 'teacher_id', 'generation_id', 'room', 'description'));

        return response()->json([
            'success' => true,
            'data'    => $class->load(['teacher.user', 'generation']),
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
            'room'          => 'nullable|string|max:50',
            'description'   => 'nullable|string',
            'is_active'     => 'boolean',
        ]);

        $class->update($request->only('name', 'teacher_id', 'generation_id', 'room', 'description', 'is_active'));

        return response()->json([
            'success' => true,
            'data'    => $class->fresh()->load(['teacher.user', 'generation']),
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
