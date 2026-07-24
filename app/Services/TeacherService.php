<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TeacherService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    /**
     * Get paginated teachers with search and filter criteria.
     */
    public function listTeachers(array $params): LengthAwarePaginator
    {
        $query = Teacher::with(['user', 'department'])->withCount(['subjects', 'classes']);

        // Search by teacher name (through user relation) or email
        if (!empty($params['search'])) {
            $search = $params['search'];
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by department
        if (!empty($params['department_id'])) {
            $query->where('department_id', $params['department_id']);
        }

        // Filter by user status
        if (!empty($params['status'])) {
            $query->whereHas('user', function ($q) use ($params) {
                $q->where('status', $params['status']);
            });
        }

        // Filter by gender
        if (!empty($params['gender'])) {
            $query->whereHas('user', function ($q) use ($params) {
                $q->where('gender', $params['gender']);
            });
        }

        $perPage = (int) ($params['per_page'] ?? 10);

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get a single teacher with relations.
     */
    public function getTeacher(Teacher $teacher): Teacher
    {
        return $teacher->load(['user', 'department', 'subjects'])->loadCount(['subjects', 'classes']);
    }

    /**
     * Create a teacher from existing user data.
     */
    public function createTeacher(array $data, ?User $actor = null): Teacher
    {
        return DB::transaction(function () use ($data, $actor) {
            // If user_id is provided, use existing user. Otherwise create a new user.
            if (!empty($data['user_id'])) {
                $user = User::findOrFail($data['user_id']);
            } else {
                // Create a new user with teacher role
                $roleId = \App\Models\RBAC\Role::where('slug', 'teacher')->value('id');
                $user = User::create([
                    'name'     => $data['name'],
                    'email'    => $data['email'],
                    'password' => Hash::make($data['password'] ?? 'password'),
                    'role_id'  => $roleId,
                    'gender'   => $data['gender'] ?? null,
                    'status'   => $data['status'] ?? 'active',
                ]);
            }

            $teacher = Teacher::create([
                'user_id'       => $user->id,
                'department_id' => $data['department_id'] ?? null,
            ]);

            $teacher->load(['user', 'department']);

            $this->activityLogService->logCreate(
                $actor,
                'Teachers',
                "Created teacher '{$user->name}' ({$user->email}).",
                $teacher,
                ['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'department_id' => $teacher->department_id]
            );

            return $teacher;
        });
    }

    /**
     * Update a teacher.
     */
    public function updateTeacher(Teacher $teacher, array $data, ?User $actor = null): Teacher
    {
        return DB::transaction(function () use ($teacher, $data, $actor) {
            $oldData = [
                'department_id' => $teacher->department_id,
            ];

            if (array_key_exists('department_id', $data)) {
                $teacher->update(['department_id' => $data['department_id']]);
            }

            // Update user fields if provided
            if ($teacher->user && array_intersect_key($data, array_flip(['name', 'email', 'gender', 'status']))) {
                $userUpdate = [];
                if (isset($data['name'])) $userUpdate['name'] = $data['name'];
                if (isset($data['email'])) $userUpdate['email'] = $data['email'];
                if (isset($data['gender'])) $userUpdate['gender'] = $data['gender'];
                if (isset($data['status'])) $userUpdate['status'] = $data['status'];
                if (!empty($data['password'])) $userUpdate['password'] = Hash::make($data['password']);

                if (!empty($userUpdate)) {
                    $teacher->user->update($userUpdate);
                }
            }

            $teacher->load(['user', 'department']);

            $this->activityLogService->logUpdate(
                $actor,
                'Teachers',
                "Updated teacher '{$teacher->user?->name}'.",
                $teacher,
                $oldData,
                ['department_id' => $teacher->department_id]
            );

            return $teacher;
        });
    }

    /**
     * Delete a single teacher.
     */
    public function deleteTeacher(Teacher $teacher, ?User $actor = null): void
    {
        DB::transaction(function () use ($teacher, $actor) {
            $teacherName = $teacher->user?->name ?? "Teacher #{$teacher->id}";
            $teacherEmail = $teacher->user?->email ?? '';

            $teacher->delete();

            $this->activityLogService->logDelete(
                $actor,
                'Teachers',
                "Deleted teacher '{$teacherName}' ({$teacherEmail}).",
                $teacher,
                ['name' => $teacherName, 'email' => $teacherEmail]
            );
        });
    }

    /**
     * Bulk delete teachers.
     */
    public function bulkDeleteTeachers(array $ids, ?User $actor = null): int
    {
        return DB::transaction(function () use ($ids, $actor) {
            $deletedCount = 0;
            foreach ($ids as $id) {
                $teacher = Teacher::find($id);
                if (!$teacher) continue;

                $teacherName = $teacher->user?->name ?? "Teacher #{$teacher->id}";
                $teacherEmail = $teacher->user?->email ?? '';

                $teacher->delete();

                $this->activityLogService->logDelete(
                    $actor,
                    'Teachers',
                    "Deleted teacher '{$teacherName}' ({$teacherEmail}).",
                    $teacher,
                    ['name' => $teacherName, 'email' => $teacherEmail]
                );

                $deletedCount++;
            }
            return $deletedCount;
        });
    }

    /**
     * Get all departments for filters.
     */
    public function getDepartments(): array
    {
        return \App\Models\Department::orderBy('name')->get(['id', 'name'])->toArray();
    }
}
