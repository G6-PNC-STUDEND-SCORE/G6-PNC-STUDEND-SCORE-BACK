<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    /**
     * The classes in this academic year.
     */
    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    /**
     * The students enrolled in this academic year.
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * The scores recorded in this academic year.
     */
    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }
}