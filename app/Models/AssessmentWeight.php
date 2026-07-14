<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentWeight extends Model
{
    protected $fillable = [
        'assessment_type_id',
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
}
