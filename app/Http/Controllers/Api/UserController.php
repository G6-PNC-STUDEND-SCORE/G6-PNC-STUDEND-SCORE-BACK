<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
                    'phone'      => $user->phone,
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
            'phone'    => 'nullable|string|max:20',
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
                'phone'    => $request->phone,
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
            'phone'    => 'nullable|string|max:20',
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
            'phone'   => $request->phone,
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

    // DELETE /users/{user} — delete a user
    public function destroy(Request $request, User $user): JsonResponse
    {
        // Prevent self-deletion
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 403);
        }

        $userName = $user->name;
        $userEmail = $user->email;

        $user->delete();

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
