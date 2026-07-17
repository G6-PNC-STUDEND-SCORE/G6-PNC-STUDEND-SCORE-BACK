<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        $user = $request->user()->load('role');

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
            'phone' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:255',
            'school' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
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
            'phone',
            'department',
            'school',
            'bio',
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

        $user->load('role');

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

        $data['role'] = $user->role?->slug ?? 'user';

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
