<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Score extends Model
{
    protected $fillable = [
        'student_id',
        'subject_id',
        'academic_year_id',
        'quiz',
        'assignment',
        'midterm',
        'final',
        'total',
        'grade',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'quiz' => 'decimal:2',
            'assignment' => 'decimal:2',
            'midterm' => 'decimal:2',
            'final' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * The student this score belongs to.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * The subject this score is for.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * The academic year this score was recorded in.
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}