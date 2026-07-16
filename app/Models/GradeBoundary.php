<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeBoundary extends Model
{
    protected $fillable = [
        'grade',
        'min_percent',
        'max_percent',
        'label',
        'color',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_percent' => 'decimal:2',
            'max_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public static function getGrade(float|int|null $score): ?string
    {
        if ($score === null) return null;

        return static::where('is_active', true)
            ->where('min_percent', '<=', (float) $score)
            ->where('max_percent', '>=', (float) $score)
            ->orderBy('min_percent', 'desc')
            ->value('grade');
    }

    public static function getGradeWithBoundary(float|int|null $score): ?array
    {
        if ($score === null) return null;

        return static::where('is_active', true)
            ->where('min_percent', '<=', (float) $score)
            ->where('max_percent', '>=', (float) $score)
            ->orderBy('min_percent', 'desc')
            ->first(['grade', 'min_percent', 'max_percent', 'label', 'color']);
    }
}
