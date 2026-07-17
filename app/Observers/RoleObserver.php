<?php

namespace App\Observers;

use App\Models\RBAC\Role;
use App\Services\ActivityLogService;

class RoleObserver
{
    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}

    public function created(Role $role): void
    {
        $this->activityLogService->logCreate(
            null,
            'Roles & Permissions',
            "Created role '{$role->name}' ({$role->slug}).",
            $role,
            $role->toArray()
        );
    }

    public function updated(Role $role): void
    {
        $changes = ActivityLogService::getModelChanges($role);
        if ($changes['old'] === null && $changes['new'] === null) {
            return;
        }

        $this->activityLogService->logUpdate(
            null,
            'Roles & Permissions',
            "Updated role '{$role->name}' ({$role->slug}).",
            $role,
            $changes['old'],
            $changes['new']
        );
    }

    public function deleted(Role $role): void
    {
        $this->activityLogService->logDelete(
            null,
            'Roles & Permissions',
            "Deleted role '{$role->name}' ({$role->slug}).",
            $role,
            $role->toArray()
        );
    }
}
