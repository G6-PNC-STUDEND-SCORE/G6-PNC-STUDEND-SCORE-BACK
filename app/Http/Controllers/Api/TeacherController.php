<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Services\TeacherService;
use App\Http\Resources\TeacherResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    public function __construct(
        private readonly TeacherService $teacherService
    ) {}

    // GET /teachers — list all teachers with pagination, search, and filters
    public function index(Request $request): JsonResponse
    {
        $paginated = $this->teacherService->listTeachers($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Teachers retrieved successfully.',
            'data'    => [
                'current_page' => $paginated->currentPage(),
                'data'         => TeacherResource::collection($paginated->items()),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'from'         => $paginated->firstItem(),
                'to'           => $paginated->lastItem(),
            ],
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    // GET /teachers/departments — list departments for filters
    public function departments(): JsonResponse
    {
        $departments = $this->teacherService->getDepartments();

        return response()->json([
            'success' => true,
            'data'    => $departments,
        ]);
    }

    // GET /teachers/{teacher} — show a single teacher
    public function show(Teacher $teacher): JsonResponse
    {
        $teacher = $this->teacherService->getTeacher($teacher);

        return response()->json([
            'success' => true,
            'data'    => new TeacherResource($teacher),
        ]);
    }

    // POST /teachers — create a new teacher
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'user_id'       => 'nullable|integer|exists:users,id',
            'name'          => 'required_without:user_id|string|max:255',
            'email'         => 'required_without:user_id|email|max:255|unique:users,email',
            'password'      => 'nullable|string|min:8',
            'department_id' => 'nullable|integer|exists:departments,id',
            'gender'        => 'nullable|string|in:Male,Female,Other',
            'status'        => 'nullable|string|in:active,inactive,suspended',
        ]);

        $teacher = $this->teacherService->createTeacher($request->all(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Teacher created successfully.',
            'data'    => new TeacherResource($teacher),
        ], 201);
    }

    // PUT /teachers/{teacher} — update a teacher
    public function update(Request $request, Teacher $teacher): JsonResponse
    {
        $request->validate([
            'department_id' => 'nullable|integer|exists:departments,id',
            'name'          => 'nullable|string|max:255',
            'email'         => 'nullable|email|max:255|unique:users,email,' . $teacher->user_id,
            'password'      => 'nullable|string|min:8',
            'gender'        => 'nullable|string|in:Male,Female,Other',
            'status'        => 'nullable|string|in:active,inactive,suspended',
        ]);

        $teacher = $this->teacherService->updateTeacher($teacher, $request->all(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Teacher updated successfully.',
            'data'    => new TeacherResource($teacher),
        ]);
    }

    // DELETE /teachers/{teacher} — delete a teacher
    public function destroy(Request $request, Teacher $teacher): JsonResponse
    {
        $this->teacherService->deleteTeacher($teacher, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Teacher deleted successfully.',
        ]);
    }

    // POST /teachers/bulk-delete — delete multiple teachers
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'required|integer|exists:teachers,id',
        ]);

        $count = $this->teacherService->bulkDeleteTeachers($request->ids, $request->user());

        return response()->json([
            'success' => true,
            'message' => "{$count} teacher(s) deleted successfully.",
            'data'    => ['deleted_count' => $count],
        ]);
    }
}
