<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Score extends Model
{
    protected $fillable = [
        'student_id',
        'subject_id',
        'term_id',
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

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(ScoreDetail::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(ScoreDetail::class)->where('type', 'quiz');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ScoreDetail::class)->where('type', 'assignment');
    }

    public function midterms(): HasMany
    {
        return $this->hasMany(ScoreDetail::class)->where('type', 'midterm');
    }

    public function finals(): HasMany
    {
        return $this->hasMany(ScoreDetail::class)->where('type', 'final');
    }
}
