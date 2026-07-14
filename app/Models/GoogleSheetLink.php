<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoogleSheetLink extends Model
{
    protected $fillable = [
        'subject_id',
        'term_id',
        'created_by',
        'spreadsheet_id',
        'spreadsheet_url',
        'spreadsheet_name',
        'last_sync_at',
        'last_updated_records',
        'last_failed_records',
        'sync_status',
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(GoogleSyncLog::class)->latest();
    }
}
