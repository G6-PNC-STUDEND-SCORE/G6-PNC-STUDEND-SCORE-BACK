<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RBAC\Role;
use App\Models\User;
use App\Notifications\PasswordResetNotification;
use Google\Client as GoogleClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email', 'password' => 'required|string']);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'The provided credentials are incorrect.'], 401);
        }

        return response()->json([
            'user' => $this->userData($user),
            'token' => $user->createToken('api-token')->plainTextToken,
            'message' => 'Login successful',
        ]);
    }

    public function googleLogin(Request $request): JsonResponse
    {
        $request->validate([
            'credential' => 'required|string',
        ]);

        $clientId = config('services.google.client_id');

        if (! $clientId) {
            Log::error('Google client ID is not configured');
            return response()->json([
                'message' => 'Google login is not configured.',
            ], 500);
        }

        try {
            $client = new GoogleClient(['client_id' => $clientId]);
            $payload = $client->verifyIdToken($request->credential);

            if (! $payload) {
                return response()->json([
                    'message' => 'Invalid Google credential.',
                ], 401);
            }

            $googleId = $payload['sub'];
            $email = $payload['email'];
            $name = $payload['name'] ?? explode('@', $email)[0];

            // Find or create user by google_id or email
            $user = User::where('google_id', $googleId)->first();

            if (! $user) {
                // Try to find by email
                $user = User::where('email', $email)->first();

                if ($user) {
                    // Link Google account to existing user
                    $user->update(['google_id' => $googleId]);
                } else {
                    // Create a new user
                    $user = User::create([
                        'name' => $name,
                        'email' => $email,
                        'password' => Hash::make(Str::random(32)),
                        'google_id' => $googleId,
                    ]);

                    // Assign the teacher role via pivot table
                    $teacherRole = Role::where('slug', 'teacher')->first();
                    if ($teacherRole) {
                        $user->roles()->attach($teacherRole);
                    }
                }
            }

            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'user' => $this->userData($user),
                'token' => $token,
                'message' => 'Login successful',
            ]);
        } catch (\Exception $e) {
            Log::error('Google token verification failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to verify Google credential.',
            ], 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userData($request->user())]);
    }

    /**
     * Build a consistent user payload for the frontend.
     * The SPA expects `role` to be a string (slug), not the relation object.
     */
    private function userData(User $user): array
    {
        $user->load('role');
        $data = $user->toArray();
        unset($data['role']);
        $data['role'] = $user->role?->slug ?? 'user';
        $data['permissions'] = $user->isAdmin()
            ? \App\Models\RBAC\Permission::pluck('slug')->all()
            : $user->role?->permissions->pluck('slug')->all() ?? [];

        return $data;
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required|string',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Current password is incorrect.'], 400);
        }

        if ($request->current_password === $request->new_password) {
            return response()->json(['success' => false, 'message' => 'The new password must be different from the current password.'], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['success' => true, 'message' => 'Password changed successfully.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink(
            $request->only('email'),
            fn (User $user, string $token) => $user->notify(new PasswordResetNotification($token))
        );

        return response()->json(['message' => 'Password reset link sent to your email.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            fn (User $user, string $password) => $user->forceFill(['password' => Hash::make($password)])->save()
        );

        return match ($status) {
            Password::PASSWORD_RESET => response()->json(['message' => 'Password has been reset successfully.']),
            Password::INVALID_TOKEN => response()->json(['message' => 'Invalid or expired reset token.'], 400),
            Password::INVALID_USER => response()->json(['message' => 'Unable to find user with that email.'], 400),
            default => response()->json(['message' => 'Unable to reset password.'], 500),
        };
    }
}

