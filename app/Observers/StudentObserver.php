<?php

namespace App\Observers;

use App\Models\Student;
use App\Services\ActivityLogService;

class StudentObserver
{
    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}

    /**
     * Handle the Student "created" event.
     */
    public function created(Student $student): void
    {
        $this->activityLogService->logCreate(
            null, // Will use auth()->user() internally
            'Students',
            "Created student {$student->student_number} ({$student->user?->name}).",
            $student,
            $student->toArray()
        );
    }

    /**
     * Handle the Student "updated" event.
     */
    public function updated(Student $student): void
    {
        $changes = ActivityLogService::getModelChanges($student);

        if ($changes['old'] === null && $changes['new'] === null) {
            return; // No meaningful changes
        }

        $this->activityLogService->logUpdate(
            null,
            'Students',
            "Updated student {$student->student_number} ({$student->user?->name}).",
            $student,
            $changes['old'],
            $changes['new']
        );
    }

    /**
     * Handle the Student "deleted" event.
     */
    public function deleted(Student $student): void
    {
        $this->activityLogService->logDelete(
            null,
            'Students',
            "Deleted student {$student->student_number} ({$student->user?->name}).",
            $student,
            $student->toArray()
        );
    }
}