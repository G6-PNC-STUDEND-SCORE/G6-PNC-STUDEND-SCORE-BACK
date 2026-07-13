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
        'order_number',
        'max_score',
        'mark',
    ];

    protected function casts(): array
    {
        return [
            'mark' => 'decimal:2',
            'max_score' => 'integer',
            'order_number' => 'integer',
        ];
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
