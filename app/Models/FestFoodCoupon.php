<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestFoodCoupon extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'event_id', 'school_id', 'coupon_code', 'meal_type', 'valid_date',
        'head_count', 'status', 'issued_at', 'redeemed_at', 'notes',
    ];

    protected $casts = [
        'valid_date'   => 'date',
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

    public static function generateCode(FestEvent $event): string
    {
        $prefix = 'FC'.$event->id;
        $n = static::where('event_id', $event->id)->count() + 1;

        return $prefix.'-'.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}
