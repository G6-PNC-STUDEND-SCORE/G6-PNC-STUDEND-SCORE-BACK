<?php

namespace App\Services;

use App\Models\User;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Models\StudentSubjectEnrollment;
use App\Models\ReportCard;
use App\Models\Transcript;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly StudentNumberService $studentNumberService
    ) {}

    /**
     * Get paginated users with search and filter criteria.
     */
    public function listUsers(array $params): LengthAwarePaginator
    {
        $query = User::with('role:id,name,slug');

        // Search name, email or role
        if (!empty($params['search'])) {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhereHas('role', function ($roleQuery) use ($search) {
                      $roleQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('slug', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by role slug or role_id
        if (!empty($params['role'])) {
            $role = $params['role'];
            $query->whereHas('role', function ($q) use ($role) {
                $q->where('slug', $role)->orWhere('name', $role);
            });
        } elseif (!empty($params['role_id'])) {
            $query->where('role_id', $params['role_id']);
        }

        // Filter by status
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        $perPage = (int) ($params['per_page'] ?? 10);

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Create a user and log activity.
     */
    public function createUser(array $data, ?User $actor = null): User
    {
        return DB::transaction(function () use ($data, $actor) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => Hash::make($data['password']),
                'role_id'  => $data['role_id'],
                'gender'   => $data['gender'] ?? null,
                'status'   => $data['status'] ?? 'active',
            ]);

            $user->load('role:id,name,slug');

            // Auto-create Student record if the user has the student role
            if ($user->role?->slug === 'student') {
                $intakeYear = now()->year;
                $studentIdNumber = $this->studentNumberService->createSequence($intakeYear);

                Student::create([
                    'user_id'          => $user->id,
                    'student_id_number' => $studentIdNumber,
                ]);
            }

            $this->activityLogService->logCreate(
                $actor,
                'Users',
                "Created user '{$user->name}' ({$user->email}) with role '{$user->role?->name}'.",
                $user,
                ['name' => $user->name, 'email' => $user->email, 'role_id' => $user->role_id]
            );

            return $user;
        });
    }

    /**
     * Update a user and log activity.
     */
    public function updateUser(User $user, array $data, ?User $actor = null): User
    {
        return DB::transaction(function () use ($user, $data, $actor) {
            $oldData = [
                'name'    => $user->name,
                'email'   => $user->email,
                'role_id' => $user->role_id,
                'status'  => $user->status,
            ];

            $updateData = [
                'name'    => $data['name'],
                'email'   => $data['email'],
                'role_id' => $data['role_id'],
                'gender'  => $data['gender'] ?? null,
                'status'  => $data['status'] ?? $user->status,
            ];

            if (!empty($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            $user->update($updateData);
            $user->load('role:id,name,slug');

            // Auto-create Student record if the user now has the student role
            // and doesn't already have one (e.g. role changed from teacher to student)
            if ($user->role?->slug === 'student' && !$user->student()->exists()) {
                $intakeYear = now()->year;
                $studentIdNumber = $this->studentNumberService->createSequence($intakeYear);

                Student::create([
                    'user_id'          => $user->id,
                    'student_id_number' => $studentIdNumber,
                ]);
            }

            $this->activityLogService->logUpdate(
                $actor,
                'Users',
                "Updated user '{$user->name}' ({$user->email}).",
                $user,
                $oldData,
                ['name' => $user->name, 'email' => $user->email, 'role_id' => $user->role_id, 'status' => $user->status]
            );

            return $user;
        });
    }

    /**
     * Delete a single user safely cleaning up student dependencies.
     */
    public function deleteUser(User $user, ?User $actor = null): void
    {
        DB::transaction(function () use ($user, $actor) {
            $this->cleanupUserRelations($user);

            $userName = $user->name;
            $userEmail = $user->email;

            $user->delete();

            $this->activityLogService->logDelete(
                $actor,
                'Users',
                "Deleted user '{$userName}' ({$userEmail}).",
                $user,
                ['name' => $userName, 'email' => $userEmail]
            );
        });
    }

    /**
     * Bulk delete users safely cleaning up student dependencies.
     * Returns the count of deleted users.
     */
    public function bulkDeleteUsers(array $ids, ?User $actor = null): int
    {
        return DB::transaction(function () use ($ids, $actor) {
            $deletedCount = 0;
            foreach ($ids as $id) {
                $user = User::find($id);
                if (!$user) {
                    continue;
                }

                // Prevent self-deletion if specified actor matches target user ID
                if ($actor && $user->id === $actor->id) {
                    continue;
                }

                $this->cleanupUserRelations($user);

                $userName = $user->name;
                $userEmail = $user->email;

                $user->delete();

                $this->activityLogService->logDelete(
                    $actor,
                    'Users',
                    "Deleted user '{$userName}' ({$userEmail}).",
                    $user,
                    ['name' => $userName, 'email' => $userEmail]
                );

                $deletedCount++;
            }
            return $deletedCount;
        });
    }

    /**
     * Helper to clean up student dependencies before user deletion.
     */
    private function cleanupUserRelations(User $user): void
    {
        $student = $user->student;
        if ($student) {
            // Get class history IDs for this student
            $classHistoryIds = StudentClassHistory::where('student_id', $student->id)->pluck('id');

            // Delete enrollments referencing these class histories (by class_history_id)
            $enrollmentsByHistory = StudentSubjectEnrollment::whereIn('student_class_history_id', $classHistoryIds)->get();
            foreach ($enrollmentsByHistory as $enrollment) {
                if ($enrollment->score) {
                    $enrollment->score->details()->delete();
                    $enrollment->score->delete();
                }
                $enrollment->delete();
            }

            // Also delete any remaining enrollments by student_id
            $enrollments = StudentSubjectEnrollment::where('student_id', $student->id)->get();
            foreach ($enrollments as $enrollment) {
                if ($enrollment->score) {
                    $enrollment->score->details()->delete();
                    $enrollment->score->delete();
                }
                $enrollment->delete();
            }

            // Delete class histories
            StudentClassHistory::where('student_id', $student->id)->delete();

            // Delete report cards and transcripts
            ReportCard::where('student_id', $student->id)->delete();
            Transcript::where('student_id', $student->id)->delete();

            // Delete the student
            $student->delete();
        }
    }
}
