<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RBAC\Permission;
use App\Models\RBAC\Role;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    // GET /permissions — list all permissions grouped by feature
    public function index(): JsonResponse
    {
        $permissions = Permission::orderBy('group')->orderBy('name')->get()
            ->groupBy('group');

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }

    // GET /roles — list all roles with their permissions
    public function roles(): JsonResponse
    {
        $roles = Role::with('permissions')->get();
        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    // GET /roles/{role}/permissions — get permissions for a specific role
    public function rolePermissions(Role $role): JsonResponse
    {
        return response()->json([
            'role'        => $role,
            'permissions' => $role->load('permissions')->permissions->groupBy('group'),
        ]);
    }

    // PUT /roles/{role}/permissions — sync permissions for a role
    // Body: { permission_ids: [1, 2, 3, ...] }
    public function syncRolePermissions(Request $request, Role $role): JsonResponse
    {
        $request->validate([
            'permission_ids'   => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $oldPermissionIds = $role->permissions()->pluck('permissions.id')->toArray();
        $role->permissions()->sync($request->permission_ids);

        // Clear the cached permissions so gates update immediately
        \App\Providers\AuthServiceProvider::clearPermissionsCache();

        // Log permission sync
        $role->load('permissions');
        $newPermNames = $role->permissions->pluck('name')->implode(', ');
        app(ActivityLogService::class)->log(
            null,
            'SyncPermissions',
            'Roles & Permissions',
            "Synced permissions for role '{$role->name}': {$newPermNames}",
            $role,
            ['permission_ids' => $oldPermissionIds],
            ['permission_ids' => $request->permission_ids]
        );

        return response()->json([
            'success' => true,
            'message' => "Permissions updated for role: {$role->name}",
            'data' => [
                'role'        => $role->name,
                'permissions' => $role->permissions->groupBy('group'),
            ],
        ]);
    }

    // POST /roles/{role}/permissions/{permission} — grant a single permission to a role
    public function grantPermission(Role $role, Permission $permission): JsonResponse
    {
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        app(ActivityLogService::class)->log(
            null,
            'GrantPermission',
            'Roles & Permissions',
            "Granted permission '{$permission->name}' to role '{$role->name}'.",
            $role,
            null,
            ['permission_id' => $permission->id, 'permission_slug' => $permission->slug]
        );

        return response()->json([
            'message' => "Permission '{$permission->name}' granted to role '{$role->name}'.",
        ]);
    }

    // DELETE /roles/{role}/permissions/{permission} — revoke a single permission from a role
    public function revokePermission(Role $role, Permission $permission): JsonResponse
    {
        $role->permissions()->detach($permission->id);

        app(ActivityLogService::class)->log(
            null,
            'RevokePermission',
            'Roles & Permissions',
            "Revoked permission '{$permission->name}' from role '{$role->name}'.",
            $role,
            ['permission_id' => $permission->id, 'permission_slug' => $permission->slug],
            null
        );

        return response()->json([
            'message' => "Permission '{$permission->name}' revoked from role '{$role->name}'.",
        ]);
    }

    // PUT /roles/{role} — update a role's name/description
    public function update(Request $request, Role $role): JsonResponse
    {
        $request->validate([
            'name'        => 'required|string|max:255|unique:roles,name,' . $role->id,
            'description' => 'nullable|string|max:1000',
        ]);

        $role->update($request->only('name', 'description'));

        return response()->json([
            'success' => true,
            'message' => "Role '{$role->name}' updated successfully.",
            'data'    => $role->fresh(),
        ]);
    }

    // DELETE /roles/{role} — delete a role (except admin)
    public function destroy(Role $role): JsonResponse
    {
        if ($role->slug === 'admin') {
            return response()->json(['message' => 'Admin role cannot be deleted.'], 403);
        }

        $role->delete();

        // Clear the cached permissions
        \App\Providers\AuthServiceProvider::clearPermissionsCache();

        return response()->json([
            'success' => true,
            'message' => "Role '{$role->name}' deleted successfully.",
        ]);
    }

    // POST /roles — create a new role with optional permissions
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'            => 'required|string|max:255|unique:roles,name',
            'description'     => 'nullable|string|max:1000',
            'permission_ids'  => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role = Role::create($request->only('name', 'description'));

        // Sync permissions if provided
        if ($request->has('permission_ids')) {
            $role->permissions()->sync($request->permission_ids);

            // Clear the cached permissions so gates update immediately
            \App\Providers\AuthServiceProvider::clearPermissionsCache();

            // Log permission assignment at creation
            $role->load('permissions');
            $permNames = $role->permissions->pluck('name')->implode(', ');
            app(ActivityLogService::class)->log(
                null,
                'AssignPermissions',
                'Roles & Permissions',
                "Assigned permissions to new role '{$role->name}': {$permNames}",
                $role,
                null,
                ['permission_ids' => $request->permission_ids]
            );
        }

        return response()->json([
            'success' => true,
            'message' => "Role '{$role->name}' created successfully.",
            'data' => $role,
        ], 201);
    }
}
