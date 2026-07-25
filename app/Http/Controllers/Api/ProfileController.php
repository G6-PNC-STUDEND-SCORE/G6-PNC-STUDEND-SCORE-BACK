<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * Get the authenticated user's profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('role.permissions');

        return response()->json([
            'success' => true,
            'data' => $this->profileData($user),
        ]);
    }

    /**
     * Update the authenticated user's profile.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
            'bio' => 'nullable|string|max:1000',
            'gender' => 'nullable|in:Male,Female,Other',
            'date_of_birth' => 'nullable|date|before:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user->update($request->only([
            'name',
            'email',
            'bio',
            'gender',
            'date_of_birth',
        ]));

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            $avatarFile = $request->file('avatar');

            $validator = Validator::make(['avatar' => $avatarFile], [
                'avatar' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Delete old avatar if exists
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            $path = $avatarFile->store('avatars', 'public');
            $user->avatar = $path;
            $user->save();
        }

        $user->load('role.permissions');

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $this->profileData($user),
        ]);
    }

    /**
     * Build a consistent profile payload for the frontend.
     * The SPA expects `role` to be a string (slug), not the relation object.
     */
    private function profileData(User $user): array
    {
        $data = $user->toArray();

        // Remove the nested relation so the string `role` below is authoritative.
        unset($data['role']);

        $roleSlug = $user->role?->slug ?? 'user';
        $data['role'] = $roleSlug;
        $data['permissions'] = $user->role?->permissions->pluck('slug')->all() ?? [];

        // Role-specific info the profile page shows beyond the shared user fields —
        // a teacher cares about what they teach, a student about their own enrollment.
        if ($roleSlug === 'teacher') {
            $teacher = $user->teacher()->with(['department', 'classes'])->first();
            if ($teacher) {
                $data['teacher_info'] = [
                    'department' => $teacher->department?->name,
                    'classes' => $teacher->classes->pluck('name')->all(),
                    'subjects' => Subject::whereHas('teachers', fn ($q) => $q->where('teachers.id', $teacher->id))
                        ->pluck('name')->all(),
                ];
            }
        } elseif ($roleSlug === 'student') {
            $student = $user->student()->with(['generation', 'classHistories.class'])->first();
            if ($student) {
                $activeClass = $student->classHistories->firstWhere('status', 'active');
                $data['student_info'] = [
                    'student_id_number' => $student->student_id_number,
                    'generation' => $student->generation?->name,
                    'class' => $activeClass?->class?->name,
                ];
            }
        }

        return $data;
    }

    /**
     * Upload/update avatar only.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Delete old avatar if exists
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->avatar = $path;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Avatar uploaded successfully',
            'data' => [
                'avatar_url' => asset('storage/' . $path),
                'avatar' => $path,
            ],
        ]);
    }
}
