<?php

namespace App\Observers;

use App\Models\Subject;
use App\Services\ActivityLogService;

class SubjectObserver
{
    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}

    public function created(Subject $subject): void
    {
        $this->activityLogService->logCreate(
            null,
            'Subjects',
            "Created subject {$subject->name} ({$subject->code}).",
            $subject,
            $subject->toArray()
        );
    }

    public function updated(Subject $subject): void
    {
        $changes = ActivityLogService::getModelChanges($subject);
        if ($changes['old'] === null && $changes['new'] === null) {
            return;
        }

        $this->activityLogService->logUpdate(
            null,
            'Subjects',
            "Updated subject {$subject->name} ({$subject->code}).",
            $subject,
            $changes['old'],
            $changes['new']
        );
    }

    public function deleted(Subject $subject): void
    {
        $this->activityLogService->logDelete(
            null,
            'Subjects',
            "Deleted subject {$subject->name} ({$subject->code}).",
            $subject,
            $subject->toArray()
        );
    }
}