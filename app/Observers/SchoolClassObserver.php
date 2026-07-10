<?php

namespace App\Observers;

use App\Models\SchoolClass;
use App\Services\ActivityLogService;

class SchoolClassObserver
{
    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}

    public function created(SchoolClass $schoolClass): void
    {
        $this->activityLogService->logCreate(
            null,
            'Classes',
            "Created class {$schoolClass->name} ({$schoolClass->code}).",
            $schoolClass,
            $schoolClass->toArray()
        );
    }

    public function updated(SchoolClass $schoolClass): void
    {
        $changes = ActivityLogService::getModelChanges($schoolClass);
        if ($changes['old'] === null && $changes['new'] === null) {
            return;
        }

        $this->activityLogService->logUpdate(
            null,
            'Classes',
            "Updated class {$schoolClass->name} ({$schoolClass->code}).",
            $schoolClass,
            $changes['old'],
            $changes['new']
        );
    }

    public function deleted(SchoolClass $schoolClass): void
    {
        $this->activityLogService->logDelete(
            null,
            'Classes',
            "Deleted class {$schoolClass->name} ({$schoolClass->code}).",
            $schoolClass,
            $schoolClass->toArray()
        );
    }
}