<?php

namespace App\Observers;

use App\Models\Score;
use App\Services\ActivityLogService;

class ScoreObserver
{
    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}

    public function created(Score $score): void
    {
        $score->loadMissing('enrollment.student.studentNumberSequence', 'enrollment.subjectOffering.subject');
        $student = $score->enrollment?->student;
        $subject = $score->enrollment?->subjectOffering?->subject;
        $studentLabel = $student?->student_number ?? "ID:{$score->student_subject_enrollment_id}";
        $subjectLabel = $subject?->name ?? 'Unknown subject';

        $this->activityLogService->logCreate(
            null,
            'Scores',
            "Created score for student {$studentLabel} in subject {$subjectLabel}.",
            $score,
            $score->toArray()
        );
    }

    public function updated(Score $score): void
    {
        $changes = ActivityLogService::getModelChanges($score);
        if ($changes['old'] === null && $changes['new'] === null) {
            return;
        }

        $score->loadMissing('enrollment.student.studentNumberSequence', 'enrollment.subjectOffering.subject');
        $student = $score->enrollment?->student;
        $subject = $score->enrollment?->subjectOffering?->subject;
        $studentLabel = $student?->student_number ?? "ID:{$score->student_subject_enrollment_id}";
        $subjectLabel = $subject?->name ?? 'Unknown subject';

        $this->activityLogService->logUpdate(
            null,
            'Scores',
            "Updated score for student {$studentLabel} in subject {$subjectLabel}.",
            $score,
            $changes['old'],
            $changes['new']
        );
    }

    public function deleted(Score $score): void
    {
        $score->loadMissing('enrollment.student.studentNumberSequence', 'enrollment.subjectOffering.subject');
        $student = $score->enrollment?->student;
        $subject = $score->enrollment?->subjectOffering?->subject;
        $studentLabel = $student?->student_number ?? "ID:{$score->student_subject_enrollment_id}";
        $subjectLabel = $subject?->name ?? 'Unknown subject';

        $this->activityLogService->logDelete(
            null,
            'Scores',
            "Deleted score for student {$studentLabel} in subject {$subjectLabel}.",
            $score,
            $score->toArray()
        );
    }
}
