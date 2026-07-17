<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Term extends Model
{
    protected $fillable = [
        'academic_year_id',
        'term_number',
        'name',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'term_number' => 'integer',
            'start_date'  => 'date',
            'end_date'    => 'date',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Score::class)->with('student');
    }
}
