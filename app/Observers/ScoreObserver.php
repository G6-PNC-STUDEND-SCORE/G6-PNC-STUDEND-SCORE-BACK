<?php

namespace App\Observers;

use App\Models\Score;
use App\Models\SubjectOffering;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Cache;

class ScoreObserver
{
    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}

    /**
     * Invalidate spreadsheet cache for the subject+term this score belongs to.
     * This ensures the cache stays fresh even when scores are modified
     * through the ScoreController or other code paths.
     */
    private function invalidateSpreadsheetForScore(Score $score): void
    {
        $score->loadMissing('enrollment.subjectOffering');
        $offering = $score->enrollment?->subjectOffering;
        if ($offering) {
            Cache::forget("spreadsheet_{$offering->subject_id}_{$offering->term_id}");
        }
    }

    public function created(Score $score): void
    {
        $this->invalidateSpreadsheetForScore($score);

        $score->loadMissing('enrollment.student', 'enrollment.subjectOffering.subject');
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
        $this->invalidateSpreadsheetForScore($score);

        $changes = ActivityLogService::getModelChanges($score);
        if ($changes['old'] === null && $changes['new'] === null) {
            return;
        }

        $score->loadMissing('enrollment.student', 'enrollment.subjectOffering.subject');
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
        $this->invalidateSpreadsheetForScore($score);

        $score->loadMissing('enrollment.student', 'enrollment.subjectOffering.subject');
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
