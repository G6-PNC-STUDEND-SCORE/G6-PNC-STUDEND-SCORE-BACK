<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Score;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Models\StudentSubjectEnrollment;
use App\Models\User;
use App\Models\RBAC\Role;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}

    // GET /users — list all users with their roles
    public function index(Request $request): JsonResponse
    {
        $query = User::with('role:id,name,slug');

        // Search filter
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Role filter
        if ($roleId = $request->get('role_id')) {
            $query->where('role_id', $roleId);
        }

        // Status filter
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20))
            ->through(function ($user) {

                return [
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'gender'     => $user->gender,
                    'status'     => $user->status,
                    'role'       => $user->role,
                    'avatar'     => $user->avatar,
                    'last_login_at' => $user->last_login_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $users,
        ]);
    }

    // GET /users/{user} — show a single user
    public function show(User $user): JsonResponse
    {
        $user->load('role:id,name,slug');

        if ($user->isTeacher()) {
            $user->load('teacher.classes', 'teacher.offerings.subject', 'teacher.offerings.class');
        }

        return response()->json([
            'success' => true,
            'data'    => $user,
        ]);
    }

    // POST /users — create a new user
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|max:255',
            'role_id'  => 'required|exists:roles,id',
            'gender'   => 'nullable|in:Male,Female,Other',
            'status'   => 'nullable|in:active,inactive,suspended',
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
                'role_id'  => $request->role_id,
                'gender'   => $request->gender,
                'status'   => $request->status ?? 'active',
            ]);

            $user->load('role:id,name,slug');

            $this->activityLogService->logCreate(
                $request->user(),
                'Users',
                "Created user '{$user->name}' ({$user->email}) with role '{$user->role?->name}'.",
                $user,
                ['name' => $user->name, 'email' => $user->email, 'role_id' => $user->role_id]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User created successfully.',
                'data'    => $user,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // PUT /users/{user} — update a user
    public function update(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|max:255',
            'role_id'  => 'required|exists:roles,id',
            'gender'   => 'nullable|in:Male,Female,Other',
            'status'   => 'nullable|in:active,inactive,suspended',
        ]);

        $oldData = [
            'name'    => $user->name,
            'email'   => $user->email,
            'role_id' => $user->role_id,
            'status'  => $user->status,
        ];

        $updateData = [
            'name'    => $request->name,
            'email'   => $request->email,
            'role_id' => $request->role_id,
            'gender'  => $request->gender,
            'status'  => $request->status ?? $user->status,
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);
        $user->load('role:id,name,slug');

        $this->activityLogService->logUpdate(
            $request->user(),
            'Users',
            "Updated user '{$user->name}' ({$user->email}).",
            $user,
            $oldData,
            ['name' => $user->name, 'email' => $user->email, 'role_id' => $user->role_id, 'status' => $user->status]
        );

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data'    => $user,
        ]);
    }

    // DELETE /users/{user} — delete a user and all associated data
    public function destroy(Request $request, User $user): JsonResponse
    {
        // Prevent self-deletion
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 403);
        }

        $userName = $user->name;
        $userEmail = $user->email;

        DB::beginTransaction();
        try {
            $student = Student::where('user_id', $user->id)->first();

            if ($student) {
                // Same cleanup pattern as StudentController::destroy() —
                // student_class_histories has restrictOnDelete, so we must
                // delete child records before the cascade can delete the student.

                // 1. Get all class history IDs for this student
                $classHistoryIds = StudentClassHistory::where('student_id', $student->id)->pluck('id');

                // 2. Delete enrollments by class_history_id (with scores + details)
                $enrollmentsByHistory = StudentSubjectEnrollment::whereIn('student_class_history_id', $classHistoryIds)->get();
                foreach ($enrollmentsByHistory as $enrollment) {
                    if ($enrollment->score) {
                        $enrollment->score->details()->delete();
                        $enrollment->score->delete();
                    }
                    $enrollment->delete();
                }

                // 3. Also delete any remaining enrollments by student_id
                $enrollmentsByStudent = StudentSubjectEnrollment::where('student_id', $student->id)->get();
                foreach ($enrollmentsByStudent as $enrollment) {
                    if ($enrollment->score) {
                        $enrollment->score->details()->delete();
                        $enrollment->score->delete();
                    }
                    $enrollment->delete();
                }

                // 4. Delete class histories (safe now — no enrollments reference them)
                StudentClassHistory::where('student_id', $student->id)->delete();

                // 5. Delete report cards & transcripts
                $student->reportCards()->delete();
                $student->transcripts()->delete();

                // 6. Delete the student record (now safe)
                $student->delete();
            }

            // 7. Finally, delete the user
            $user->delete();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete user: ' . $e->getMessage()], 500);
        }

        $this->activityLogService->logDelete(
            $request->user(),
            'Users',
            "Deleted user '{$userName}' ({$userEmail}).",
            $user,
            ['name' => $userName, 'email' => $userEmail]
        );

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.',
        ]);
    }

    // POST /users/bulk-delete — delete multiple users at once
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer|exists:users,id',
        ]);

        // Prevent self-deletion
        $ids = array_filter($request->ids, fn($id) => $id != $request->user()->id);
        if (empty($ids)) {
            return response()->json(['message' => 'No users to delete.'], 400);
        }

        $deletedCount = 0;

        DB::beginTransaction();
        try {
            foreach ($ids as $id) {
                $user = User::find($id);
                if (!$user) continue;

                $student = Student::where('user_id', $user->id)->first();

                if ($student) {
                    // Same cleanup as destroy()
                    $classHistoryIds = StudentClassHistory::where('student_id', $student->id)->pluck('id');

                    $enrollmentsByHistory = StudentSubjectEnrollment::whereIn('student_class_history_id', $classHistoryIds)->get();
                    foreach ($enrollmentsByHistory as $enrollment) {
                        if ($enrollment->score) {
                            $enrollment->score->details()->delete();
                            $enrollment->score->delete();
                        }
                        $enrollment->delete();
                    }

                    $enrollmentsByStudent = StudentSubjectEnrollment::where('student_id', $student->id)->get();
                    foreach ($enrollmentsByStudent as $enrollment) {
                        if ($enrollment->score) {
                            $enrollment->score->details()->delete();
                            $enrollment->score->delete();
                        }
                        $enrollment->delete();
                    }

                    StudentClassHistory::where('student_id', $student->id)->delete();
                    $student->reportCards()->delete();
                    $student->transcripts()->delete();
                    $student->delete();
                }

                $user->delete();
                $deletedCount++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete users: ' . $e->getMessage()], 500);
        }

        $this->activityLogService->logDelete(
            $request->user(),
            'Users',
            "Bulk deleted {$deletedCount} user(s).",
            null,
            ['deleted_count' => $deletedCount, 'ids' => $ids]
        );

        return response()->json([
            'success' => true,
            'message' => "{$deletedCount} user(s) deleted successfully.",
            'data'    => ['deleted_count' => $deletedCount],
        ]);
    }

    // GET /users/roles — list all roles for the user form dropdown
    public function roles(): JsonResponse
    {
        $roles = Role::orderBy('name')->get(['id', 'name', 'slug']);

        return response()->json([
            'success' => true,
            'data'    => $roles,
        ]);
    }
}
