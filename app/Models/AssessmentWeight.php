<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentWeight extends Model
{
    protected $fillable = ['subject_id', 'name', 'percent', 'sort_order'];

    public function subject(): ?BelongsTo
    {
        if (class_exists(Subject::class)) {
            return $this->belongsTo(Subject::class);
        }
        return null;
    }

    public function scores(): ?HasMany
    {
        // Helper if needed - no direct relation in schema
        return null;
    }
}
