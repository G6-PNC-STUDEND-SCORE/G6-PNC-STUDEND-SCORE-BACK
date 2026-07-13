<?php

namespace App\Observers;

use App\Models\Teacher;
use App\Services\ActivityLogService;

class TeacherObserver
{
    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}

    public function created(Teacher $teacher): void
    {
        $this->activityLogService->logCreate(
            null,
            'Teachers',
            "Created teacher {$teacher->teacher_code} ({$teacher->user?->name}).",
            $teacher,
            $teacher->toArray()
        );
    }

    public function updated(Teacher $teacher): void
    {
        $changes = ActivityLogService::getModelChanges($teacher);
        if ($changes['old'] === null && $changes['new'] === null) {
            return;
        }

        $this->activityLogService->logUpdate(
            null,
            'Teachers',
            "Updated teacher {$teacher->teacher_code} ({$teacher->user?->name}).",
            $teacher,
            $changes['old'],
            $changes['new']
        );
    }

    public function deleted(Teacher $teacher): void
    {
        $this->activityLogService->logDelete(
            null,
            'Teachers',
            "Deleted teacher {$teacher->teacher_code} ({$teacher->user?->name}).",
            $teacher,
            $teacher->toArray()
        );
    }
}