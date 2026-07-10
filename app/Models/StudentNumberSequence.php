<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StudentNumberSequence extends Model
{
    protected $fillable = [
        'intake_year',
        'student_number',
    ];

    protected function casts(): array
    {
        return [
            'intake_year' => 'integer',
        ];
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }
}
