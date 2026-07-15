<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_code',
        'name',
        'credits',
        'description',
        'department_id',
        'status',
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

    /**
     * Teachers qualified/assigned to teach this subject, via the subject_teacher pivot table.
     */
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'subject_teacher')
            ->withTimestamps();
    }

    /**
     * Terms this subject is assigned to, via the subject_term pivot table.
     */
    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(Term::class, 'subject_term')
            ->withTimestamps();
    }
}
