<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StudentSubjectEnrollment extends Model
{
    protected $fillable = [
        'student_class_history_id',
        'subject_offering_id',
        'status',
    ];

    public function studentClassHistory(): BelongsTo
    {
        return $this->belongsTo(StudentClassHistory::class);
    }

    public function subjectOffering(): BelongsTo
    {
        return $this->belongsTo(SubjectOffering::class);
    }

    public function score(): HasOne
    {
        return $this->hasOne(Score::class, 'student_subject_enrollment_id');
    }
}
