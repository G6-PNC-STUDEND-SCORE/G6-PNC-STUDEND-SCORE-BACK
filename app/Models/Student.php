<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = [
        'user_id',
        'student_number_sequence_id',
        'generation_id',
        'class_id',
    ];

    protected function casts(): array
    {
        return [];
    }

    public function studentNumberSequence(): BelongsTo
    {
        return $this->belongsTo(StudentNumberSequence::class);
    }

    /**
     * The user account associated with this student.
     */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The class this student belongs to.
     */
    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
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

    public function scoresByTerm(int $termId): HasMany
    {
        return $this->hasMany(Score::class)->where('term_id', $termId);
    }
}