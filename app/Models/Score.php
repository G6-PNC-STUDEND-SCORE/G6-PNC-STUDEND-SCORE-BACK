<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Score extends Model
{
    protected $fillable = [
        'student_subject_enrollment_id',
        'total',
        'grade',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'total_weighted_score' => 'decimal:2',
        ];
    }

    public function getTotalAttribute(): ?float
    {
        return $this->attributes['total_weighted_score'] ?? null;
    }

    public function setTotalAttribute($value): void
    {
        $this->attributes['total_weighted_score'] = $value;
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentSubjectEnrollment::class, 'student_subject_enrollment_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(ScoreDetail::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(ScoreDetail::class)
            ->whereHas('assessmentType', fn ($query) => $query->where('code', 'quiz'));
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ScoreDetail::class)
            ->whereHas('assessmentType', fn ($query) => $query->where('code', 'assignment'));
    }

    public function midterms(): HasMany
    {
        return $this->hasMany(ScoreDetail::class)
            ->whereHas('assessmentType', fn ($query) => $query->where('code', 'midterm'));
    }

    public function finals(): HasMany
    {
        return $this->hasMany(ScoreDetail::class)
            ->whereHas('assessmentType', fn ($query) => $query->where('code', 'final'));
    }
}
