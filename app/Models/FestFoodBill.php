<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A running per-school food tab for an event — accumulates FestFoodOrderItem lines as the
 * school orders, and FestFoodPayment rows as money comes in (prepaid in advance and/or cash
 * collected on-site both just add a payment row). Deliberately separate from
 * FestEventInvoice, which bills registration/participation fees.
 */
class FestFoodBill extends Model
{
    use BelongsToCentralTenant;

    public const STATUS_OPEN = 'open';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id', 'event_id', 'school_id', 'status', 'payment_mode',
        'payee_type', 'host_school_id',
        'amount_total', 'amount_paid', 'notes', 'settled_at', 'settled_by_user_id',
    ];

    protected $casts = [
        'amount_total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'settled_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsToCentralTenant('school_id');
    }

    /** The school food payments should physically go to, when payee_type is 'host_school'. */
    public function hostSchool(): BelongsTo
    {
        return $this->belongsToCentralTenant('host_school_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(FestFoodOrderItem::class, 'bill_id')->orderBy('menu_date')->orderBy('meal_type');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FestFoodPayment::class, 'bill_id')->orderByDesc('received_at');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('fest_food_bills.tenant_id', $tenantId);
    }

    public function balanceDue(): float
    {
        return round((float) $this->amount_total - (float) $this->amount_paid, 2);
    }

    /** Re-derive amount_total from order items and amount_paid from payments. Call after any mutation. */
    public function recalculate(): void
    {
        $this->amount_total = $this->orderItems()->sum('line_total');
        $this->amount_paid = $this->payments()->sum('amount');
        $this->save();
    }

    /**
     * Get (or create) the running bill for a school on an event. Payee snapshot is taken
     * from the event's current food_payee_type/food_host_school_id the first time a bill is
     * created for this school — later event-setting changes won't retroactively move where
     * an already-open bill is payable to.
     */
    public static function firstOrCreateForSchool(FestEvent $event, string $schoolId): self
    {
        return static::query()->firstOrCreate(
            ['event_id' => $event->id, 'school_id' => $schoolId],
            [
                'tenant_id' => $event->tenant_id,
                'status' => self::STATUS_OPEN,
                'payment_mode' => 'prepaid',
                'payee_type' => $event->food_payee_type ?: 'sahodaya',
                'host_school_id' => $event->food_payee_type === 'host_school' ? $event->food_host_school_id : null,
            ]
        );
    }
}
