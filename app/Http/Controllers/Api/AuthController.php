<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailDomainRule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\PasswordResetNotification;
use App\Services\Auth\GoogleIdTokenVerifierInterface;
use App\Services\StudentNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(
        private readonly GoogleIdTokenVerifierInterface $googleIdTokenVerifier
    ) {}

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
            $payload = $this->googleIdTokenVerifier->verify($request->credential, $clientId);

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
                    // Brand-new sign-in: the account's role is decided purely by its email
                    // domain (admin-managed via the "Sign-in Domains" rules) — unrecognized
                    // domains are rejected outright rather than defaulting to any role.
                    $emailDomain = Str::lower(Str::after($email, '@'));
                    $rule = EmailDomainRule::active()->where('domain', $emailDomain)->first();

                    if (! $rule) {
                        return response()->json([
                            'message' => "This Google account isn't authorized to sign in.",
                        ], 403);
                    }

                    $user = DB::transaction(function () use ($name, $email, $googleId, $rule) {
                        $user = User::create([
                            'name' => $name,
                            'email' => $email,
                            'password' => Hash::make(Str::random(32)),
                            'google_id' => $googleId,
                            'role_id' => $rule->role_id,
                            'status' => 'active',
                        ]);

                        if ($rule->role->slug === 'student') {
                            $studentNumber = app(StudentNumberService::class)->createSequence(now()->year);
                            Student::create([
                                'user_id' => $user->id,
                                'student_id_number' => $studentNumber,
                            ]);
                        } elseif ($rule->role->slug === 'teacher') {
                            Teacher::create(['user_id' => $user->id]);
                        }

                        return $user;
                    });
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
        // Always reflect the role's actual assigned permissions — including for admin, whose
        // role starts with every permission granted (see PermissionSeeder) but can be trimmed
        // from the Roles & Permissions page like any other role. This only affects what the
        // frontend shows/hides (e.g. sidebar links); backend endpoints still let admin through
        // unconditionally (User::hasPermission()), so admin can never lock themselves out —
        // this is a personal "what do I want cluttering my own nav" preference, not an actual
        // access restriction.
        $data['permissions'] = $user->role?->permissions->pluck('slug')->all() ?? [];

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

