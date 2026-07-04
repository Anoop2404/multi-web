<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestSchoolEventFee extends Model
{
    protected $fillable = [
        'event_id', 'school_id', 'school_registration_fee', 'student_registration_fee',
        'participation_item_count', 'participation_fee', 'extra_item_fee', 'total_due',
        'override_amount', 'fee_receipt_id', 'status',
    ];

    protected $casts = [
        'school_registration_fee' => 'decimal:2',
        'student_registration_fee' => 'decimal:2',
        'participation_fee' => 'decimal:2',
        'extra_item_fee' => 'decimal:2',
        'total_due' => 'decimal:2',
        'override_amount' => 'decimal:2',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'school_id');
    }

    public function feeReceipt(): BelongsTo
    {
        return $this->belongsTo(FeeReceipt::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FestSchoolEventFeeLine::class, 'fest_school_event_fee_id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
