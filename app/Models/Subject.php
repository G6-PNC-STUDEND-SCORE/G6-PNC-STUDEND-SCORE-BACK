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
        'description',
        'image',
    ];

    protected $casts = [
        'credits' => 'integer',
    ];

    // Accessor for credits (alias for credit_hours compatibility)
    public function getCreditHoursAttribute()
    {
        return $this->credits;
    }

    // Mutator for credits (alias for credit_hours compatibility)
    public function setCreditHoursAttribute($value)
    {
        $this->attributes['credits'] = $value;
    }

    // Accessor to convert status to is_active format
    public function getIsActiveAttribute()
    {
        return $this->status == 'Active' ? 'Active' : 'Inactive';
    }

    // Mutator to convert is_active to status format
    public function setIsActiveAttribute($value)
    {
        $this->attributes['status'] = in_array($value, ['Active', 1, '1', true]) ? 'Active' : 'Inactive';
    }
}
