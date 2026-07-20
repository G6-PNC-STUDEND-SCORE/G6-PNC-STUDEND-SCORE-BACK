<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\RBAC\Role;
use App\Services\UserService;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    // GET /users — list all users with pagination, search, and filters
    public function index(Request $request): JsonResponse
    {
        $paginated = $this->userService->listUsers($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully.',
            'data' => [
                'current_page' => $paginated->currentPage(),
                'data'         => UserResource::collection($paginated->items()),
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
            ]
        ]);
    }

    // GET /users/{user} — show a single user
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new UserResource($user->load('role:id,name,slug')),
        ]);
    }

    // POST /users — create a new user
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->createUser($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data'    => new UserResource($user),
        ], 201);
    }

    // PUT /users/{user} — update a user
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $updatedUser = $this->userService->updateUser($user, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data'    => new UserResource($updatedUser),
        ]);
    }

    // DELETE /users/{user} — delete a user
    public function destroy(Request $request, User $user): JsonResponse
    {
        // Prevent self-deletion
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 403);
        }

        $this->userService->deleteUser($user, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.',
        ]);
    }

    // DELETE /users/bulk-delete — delete multiple users
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:users,id',
        ]);

        $ids = $request->ids;

        // Prevent self-deletion in bulk delete
        if (in_array($request->user()->id, $ids)) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account, even in bulk requests.'
            ], 403);
        }

        $count = $this->userService->bulkDeleteUsers($ids, $request->user());

        return response()->json([
            'success' => true,
            'message' => "{$count} user(s) deleted successfully.",
            'data' => ['deleted_count' => $count],
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
