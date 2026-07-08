<?php

namespace App\Observers;

use App\Models\Classe;
use App\Services\ActivityLogService;

class ClasseObserver
{
    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}

    public function created(Classe $classe): void
    {
        $this->activityLogService->logCreate(
            null,
            'Classes',
            "Created class {$classe->name} ({$classe->code}).",
            $classe,
            $classe->toArray()
        );
    }

    public function updated(Classe $classe): void
    {
        $changes = ActivityLogService::getModelChanges($classe);
        if ($changes['old'] === null && $changes['new'] === null) {
            return;
        }

        $this->activityLogService->logUpdate(
            null,
            'Classes',
            "Updated class {$classe->name} ({$classe->code}).",
            $classe,
            $changes['old'],
            $changes['new']
        );
    }

    public function deleted(Classe $classe): void
    {
        $this->activityLogService->logDelete(
            null,
            'Classes',
            "Deleted class {$classe->name} ({$classe->code}).",
            $classe,
            $classe->toArray()
        );
    }
}