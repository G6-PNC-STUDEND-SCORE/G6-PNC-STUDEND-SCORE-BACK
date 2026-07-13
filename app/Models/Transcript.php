<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transcript extends Model
{
    protected $fillable = [
        'student_id',
        'generation_id',
        'overall_average',
        'overall_grade',
        'status',
        'generated_by',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'overall_average' => 'decimal:2',
            'generated_at'    => 'datetime',
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

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
