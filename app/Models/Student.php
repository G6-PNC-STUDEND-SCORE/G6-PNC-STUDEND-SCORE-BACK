<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = [
        'user_id',
        'student_number',
        'intake_year',
        'sequence_number',
        'class_id',
        'academic_year_id',
        'enrollment_date',
    ];

    protected function casts(): array
    {
        return [
            'enrollment_date' => 'date',
            'intake_year' => 'integer',
            'sequence_number' => 'integer',
        ];
    }

    /**
     * The user account associated with this student.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The class this student belongs to.
     */
    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'class_id');
    }

    /**
     * The academic year this student is enrolled in.
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * The scores recorded for this student.
     */
    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }

    /**
     * Get the formatted student number (e.g., PNC2026-001).
     */
    public function getFormattedStudentNumberAttribute(): string
    {
        return $this->student_number;
    }

    /**
     * Scope a query to filter by intake year.
     */
    public function scopeByIntakeYear($query, int $year)
    {
        return $query->where('intake_year', $year);
    }
}