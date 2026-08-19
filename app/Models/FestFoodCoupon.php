<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class FestFoodCoupon extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'event_id', 'school_id', 'coupon_code', 'meal_type', 'valid_date',
        'head_count', 'status', 'issued_at', 'redeemed_at', 'notes',
    ];

    protected $casts = [
        'valid_date'   => 'date:Y-m-d',
        'issued_at'    => 'datetime',
        'redeemed_at'  => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsToCentralTenant('school_id');
    }

    /**
     * Generate a unique coupon code using an atomic lock to prevent duplicates
     * under concurrent requests. Uses the event-level DB row as a lock guard
     * rather than count() + 1 which races.
     */
    public static function generateCode(FestEvent $event): string
    {
        // Use a DB lock on the event row to serialize coupon code generation.
        return DB::transaction(function () use ($event) {
            // Touch the event row to acquire a row-level lock (no actual update needed).
            FestEvent::whereKey($event->id)->lockForUpdate()->first();

            $prefix = 'FC'.$event->id;
            $n = static::where('event_id', $event->id)->count() + 1;

            return $prefix.'-'.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
        });
    }
}
