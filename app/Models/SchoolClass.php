<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class SchoolClass extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'name',
        'generation_id',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class, 'generation_id');
    }
}
