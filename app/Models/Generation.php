<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Generation extends Model
{
    protected $fillable = [
        'year',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'year'       => 'integer',
            'is_current' => 'boolean',
        ];
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
