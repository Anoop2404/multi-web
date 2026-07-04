<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestSchoolEventFeeLine extends Model
{
    protected $fillable = [
        'fest_school_event_fee_id', 'line_type', 'label', 'quantity',
        'unit_amount', 'amount', 'meta',
    ];

    protected $casts = [
        'unit_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'meta' => 'array',
    ];

    public function schoolEventFee(): BelongsTo
    {
        return $this->belongsTo(FestSchoolEventFee::class, 'fest_school_event_fee_id');
    }
}
