<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestEventInvoice extends Model
{
    protected $fillable = [
        'event_id', 'school_id', 'invoice_number',
        'school_registration_fee', 'participation_fee', 'total_amount',
        'participation_item_count', 'breakdown_json', 'status', 'issued_at', 'issued_by',
    ];

    protected $casts = [
        'school_registration_fee' => 'decimal:2',
        'participation_fee'       => 'decimal:2',
        'total_amount'            => 'decimal:2',
        'breakdown_json'          => 'array',
        'issued_at'                 => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'school_id');
    }

    public static function generateNumber(FestEvent $event): string
    {
        $prefix = 'FEST-'.$event->id.'-'.now()->format('ym');
        $last = static::where('invoice_number', 'like', $prefix.'%')->count();

        return $prefix.'-'.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }
}
