<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'subject_code',
        'name',
        'credits',
        'description',
        'department_id',
        'status',
        'quiz_weight',
        'assignment_weight',
        'midterm_weight',
        'final_weight',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(SubjectOffering::class);
    }
}
