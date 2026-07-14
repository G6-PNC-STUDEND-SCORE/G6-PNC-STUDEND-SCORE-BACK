<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleSyncLog extends Model
{
    protected $fillable = [
        'google_sheet_link_id',
        'triggered_by',
        'direction',
        'status',
        'updated_records',
        'failed_records',
        'error_message',
        'changes_summary',
    ];

    protected $casts = [
        'changes_summary' => 'array',
    ];

    public function link(): BelongsTo
    {
        return $this->belongsTo(GoogleSheetLink::class, 'google_sheet_link_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
