<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoreDetail extends Model
{
    protected $fillable = [
        'score_id',
        'type',
        'label',
        'mark',
    ];

    protected function casts(): array
    {
        return [
            'mark' => 'decimal:2',
        ];
    }

    public function score(): BelongsTo
    {
        return $this->belongsTo(Score::class);
    }
}
