<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestRegistration extends Model
{
    use BelongsToCentralTenant;

    /** Registrations that count toward reports and billing (excludes withdrawn/rejected). */
    public const ACTIVE_STATUSES = ['submitted', 'approved'];

    protected $fillable = [
        'event_id', 'item_id', 'school_id', 'mode', 'status',
        'fee_receipt_id', 'submitted_at',
    ];

    protected $casts = ['submitted_at' => 'datetime'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(FestEventItem::class, 'item_id');
    }

    public function feeReceipt(): BelongsTo
    {
        return $this->belongsTo(FeeReceipt::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsToCentralTenant('school_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(FestGroup::class, 'registration_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(FestParticipant::class, 'registration_id');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }
}
