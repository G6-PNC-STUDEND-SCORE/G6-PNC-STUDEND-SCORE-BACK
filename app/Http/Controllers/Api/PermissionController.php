<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RBAC\Permission;
use App\Models\RBAC\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    // GET /permissions — list all permissions grouped by feature
    public function index(): JsonResponse
    {
        $permissions = Permission::orderBy('group')->orderBy('name')->get()
            ->groupBy('group');

        return response()->json($permissions);
    }

    // GET /roles — list all roles with their permissions
    public function roles(): JsonResponse
    {
        $roles = Role::with('permissions')->get();
        return response()->json($roles);
    }

    // GET /roles/{role}/permissions — get permissions for a specific role
    public function rolePermissions(Role $role): JsonResponse
    {
        return response()->json([
            'role'        => $role,
            'permissions' => $role->load('permissions')->permissions->groupBy('group'),
        ]);
    }

    // PUT /roles/{role}/permissions — sync permissions for a role (admin sets all at once)
    // Body: { permission_ids: [1, 2, 3, ...] }
    public function syncRolePermissions(Request $request, Role $role): JsonResponse
    {
        if ($role->slug === 'admin') {
            return response()->json(['message' => 'Admin role permissions cannot be modified.'], 403);
        }

        $request->validate([
            'permission_ids'   => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role->permissions()->sync($request->permission_ids);

        return response()->json([
            'message'     => "Permissions updated for role: {$role->name}",
            'role'        => $role->name,
            'permissions' => $role->load('permissions')->permissions->groupBy('group'),
        ]);
    }

    // POST /roles/{role}/permissions/{permission} — grant a single permission to a role
    public function grantPermission(Role $role, Permission $permission): JsonResponse
    {
        if ($role->slug === 'admin') {
            return response()->json(['message' => 'Admin role permissions cannot be modified.'], 403);
        }

        $role->permissions()->syncWithoutDetaching([$permission->id]);

        return response()->json([
            'message' => "Permission '{$permission->name}' granted to role '{$role->name}'.",
        ]);
    }

    // DELETE /roles/{role}/permissions/{permission} — revoke a single permission from a role
    public function revokePermission(Role $role, Permission $permission): JsonResponse
    {
        if ($role->slug === 'admin') {
            return response()->json(['message' => 'Admin role permissions cannot be modified.'], 403);
        }

        $role->permissions()->detach($permission->id);

        return response()->json([
            'message' => "Permission '{$permission->name}' revoked from role '{$role->name}'.",
        ]);
    }
}
