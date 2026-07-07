<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
}
