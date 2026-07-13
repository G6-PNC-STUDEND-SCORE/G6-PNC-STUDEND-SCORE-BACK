<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'weight_percent',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'weight_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scoreDetails(): HasMany
    {
        return $this->hasMany(ScoreDetail::class);
    }
}
