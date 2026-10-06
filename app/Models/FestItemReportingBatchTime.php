<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestItemReportingBatchTime extends Model
{
    protected $fillable = [
        'event_id',
        'item_id',
        'batch_id',
        'report_at',
    ];

    protected $casts = [
        'report_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(FestEventItem::class, 'item_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(FestItemReportingBatch::class, 'batch_id');
    }
}
