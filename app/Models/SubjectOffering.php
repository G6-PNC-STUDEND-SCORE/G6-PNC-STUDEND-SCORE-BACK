<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectOffering extends Model
{
    protected $fillable = [
        'subject_id',
        'teacher_id',
        'class_id',
        'generation_id',
        'term_id',
        'status',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}