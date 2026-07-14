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
            'total' => 'decimal:2',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentSubjectEnrollment::class, 'student_subject_enrollment_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(ScoreDetail::class);
    }
}
