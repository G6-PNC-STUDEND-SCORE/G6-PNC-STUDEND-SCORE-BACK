<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RBAC\Role;
use App\Models\User;
use Google\Client as GoogleClient;
use Illuminate\Auth\Events\PasswordReset;
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
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
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
                'user' => $user,
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

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }
}

