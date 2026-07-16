<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoreDetail extends Model
{
    protected $appends = [
        'type',
    ];

    protected $fillable = [
        'score_id',
        'assessment_type_id',
        'label',
        'sequence_number',
        'max_score',
        'score',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'max_score' => 'integer',
            'sequence_number' => 'integer',
        ];
    }
    protected $casts = [
        'mark' => 'decimal:2',
        'max_score' => 'integer',
        'order_number' => 'integer',
    ];

    public function getMarkAttribute(): ?float
    {
        return $this->attributes['score'] ?? null;
    }

    public function setMarkAttribute($value): void
    {
        $this->attributes['score'] = $value;
    }

    public function score(): BelongsTo
    {
        return $this->belongsTo(Score::class);
    }

    public function assessmentType(): BelongsTo
    {
        return $this->belongsTo(AssessmentType::class);
    }

    public function getTypeAttribute(): ?string
    {
        return $this->assessmentType?->code;
    }
}
