<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'teacher',
        'class',
        'credits',
        'status',
        'image',
    ];

    protected $casts = [
        'credits' => 'integer',
    ];

    /**
     * The scores recorded for this subject.
     */
    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }
}