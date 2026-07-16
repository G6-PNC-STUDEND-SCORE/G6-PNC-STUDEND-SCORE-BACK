<?php

namespace App\Support;

final class AssessmentTypeDefaults
{
    public const DEFAULT_WEIGHTS = [
        'quiz' => 20,
        'assignment' => 10,
        'project' => 20,
        'midterm' => 30,
        'final' => 40,
    ];

    public static function codes(): array
    {
        return array_keys(self::DEFAULT_WEIGHTS);
    }

    public static function weights(): array
    {
        return self::DEFAULT_WEIGHTS;
    }

    public static function weightFor(string $code): int
    {
        return self::DEFAULT_WEIGHTS[$code] ?? 0;
    }
}
