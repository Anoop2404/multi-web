<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestItemReportingBatch extends Model
{
    protected $fillable = [
        'event_id', 'label', 'report_at', 'sort_order',
    ];

    protected $casts = [
        'report_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(FestRegistration::class, 'reporting_batch_id');
    }
}
