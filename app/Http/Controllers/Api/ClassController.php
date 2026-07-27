<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClassRequest;
use App\Http\Requests\UpdateClassRequest;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    use ApiResponds;

    // GET /classes — all authenticated users
    public function index(Request $request): JsonResponse
    {
        // Visibility is role/permission-scoped (view-classes), not per-teacher-assignment —
        // every teacher sees the same class list, matching what the role is granted.
        $query = SchoolClass::with(['teacher.user', 'generation'])->orderByDesc('id');

        return $this->success($query->get()->map(fn ($c) => [
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
        ]));
    }

    // POST /classes — admin only
    public function store(StoreClassRequest $request): JsonResponse
    {
        $class = SchoolClass::create($request->only('name', 'teacher_id', 'generation_id', 'room', 'description'));

        return $this->created($class->load(['teacher.user', 'generation']), 'Class created successfully');
    }

    // PUT /classes/{class} — admin only
    public function update(UpdateClassRequest $request, SchoolClass $class): JsonResponse
    {
        $class->update($request->only('name', 'teacher_id', 'generation_id', 'room', 'description', 'is_active'));

        return $this->success($class->fresh()->load(['teacher.user', 'generation']), 'Class updated successfully');
    }

    // DELETE /classes/{class} — admin only
    public function destroy(SchoolClass $class): JsonResponse
    {
        $class->delete();

        return $this->success(message: 'Class deleted successfully');
    }
}
