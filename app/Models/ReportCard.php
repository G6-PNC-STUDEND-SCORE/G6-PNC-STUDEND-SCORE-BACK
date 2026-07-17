<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCard extends Model
{
    protected $fillable = [
        'student_id',
        'generation_id',
        'term_id',
        'total_average',
        'rank_in_class',
        'total_students',
        'grade',
        'remarks',
        'generated_by',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'total_average' => 'decimal:2',
            'generated_at'  => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
