<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
class Student extends Model
{

    protected $fillable = [
        'user_id',
        'student_number_sequence_id',
        'generation_id',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function getStudentNumberAttribute(): ?string
    {
        return $this->studentNumberSequence?->student_number;
    }

    public function studentNumberSequence(): BelongsTo
    {
        return $this->belongsTo(StudentNumberSequence::class);
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scores(): HasManyThrough
    {
        return $this->hasManyThrough(
            Score::class,
            StudentSubjectEnrollment::class,
            'student_id',
            'student_subject_enrollment_id'
        );
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentSubjectEnrollment::class);
    }

    public function reportCards(): HasMany
    {
        return $this->hasMany(ReportCard::class);
    }

    public function transcripts(): HasMany
    {
        return $this->hasMany(Transcript::class);
    }

    public function classHistories(): HasMany
    {
        return $this->hasMany(StudentClassHistory::class);
    }
}
