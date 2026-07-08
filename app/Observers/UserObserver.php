<?php

namespace App\Observers;

use App\Models\User;
use App\Services\ActivityLogService;

class UserObserver
{
    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}

    public function created(User $user): void
    {
        $this->activityLogService->logCreate(
            null,
            'Users',
            "Created user {$user->name} ({$user->email}).",
            $user,
            $user->toArray()
        );
    }

    public function updated(User $user): void
    {
        $changes = ActivityLogService::getModelChanges($user);
        if ($changes['old'] === null && $changes['new'] === null) {
            return;
        }

        $this->activityLogService->logUpdate(
            null,
            'Users',
            "Updated user {$user->name} ({$user->email}).",
            $user,
            $changes['old'],
            $changes['new']
        );
    }

    public function deleted(User $user): void
    {
        $this->activityLogService->logDelete(
            null,
            'Users',
            "Deleted user {$user->name} ({$user->email}).",
            $user,
            $user->toArray()
        );
    }
}