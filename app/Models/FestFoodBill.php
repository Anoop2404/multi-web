<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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

    /**
     * Remove an order item, guarded (under a row lock, like recordForBill()) against
     * dropping the bill's total below what's already been paid — Food Module audit
     * 2026-08-17, Finding 2: recalculate() derives amount_total and amount_paid
     * independently, so removing a paid-for item with no check here could settle a bill
     * with an unaccounted negative balance. Centralized here (rather than duplicated
     * across the Sahodaya/host-school/self-service controllers) for the same reason
     * recordForBill() is: so all three share identical guarantees instead of drifting.
     */
    public function removeOrderItem(FestFoodOrderItem $item): void
    {
        DB::transaction(function () use ($item) {
            $locked = static::whereKey($this->id)->lockForUpdate()->firstOrFail();

            abort_if($locked->status !== self::STATUS_OPEN, 422, 'This bill is settled/cancelled and no longer editable.');

            $newTotal = round((float) $locked->orderItems()->sum('line_total') - (float) $item->line_total, 2);
            $alreadyPaid = round((float) $locked->amount_paid, 2);
            abort_if(
                $newTotal < $alreadyPaid,
                422,
                'Cannot remove this item: it would reduce the bill total (₹'.number_format($newTotal, 2).') below the amount already paid (₹'.number_format($alreadyPaid, 2).'). Void the relevant payment first.'
            );

            $item->delete();
            $locked->recalculate();
            $this->setRawAttributes($locked->getAttributes());
        });
    }

    /**
     * Mark this bill settled under a row lock, re-checking the balance at lock time
     * rather than against a possibly-stale in-memory value (Finding 3: a concurrent
     * addItem() between this action's read and write could otherwise settle a bill with
     * a real balance still outstanding). Also rejects a negative balance, not just a
     * positive one, which the original unlocked check didn't catch.
     */
    public function settle(int $userId): void
    {
        DB::transaction(function () use ($userId) {
            $locked = static::whereKey($this->id)->lockForUpdate()->firstOrFail();
            $balance = $locked->balanceDue();

            abort_if(abs($balance) > 0.004, 422, $balance > 0
                ? 'This bill has an outstanding balance of ₹'.number_format($balance, 2).' — record the remaining payment before settling.'
                : 'This bill has a negative balance of ₹'.number_format(abs($balance), 2).' (an item was likely removed after payment) — resolve it before settling.');

            $locked->update([
                'status' => self::STATUS_SETTLED,
                'settled_at' => now(),
                'settled_by_user_id' => $userId,
            ]);
            $this->setRawAttributes($locked->getAttributes());
        });
    }

    /**
     * Reopen a settled bill. Cancelled is a terminal state, same as FestRegistration's
     * withdrawn (see cancel()'s docblock) — the original unguarded reopen() could
     * silently flip a cancelled bill back to open, contradicting that stated intent.
     */
    public function reopen(): void
    {
        DB::transaction(function () {
            $locked = static::whereKey($this->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status === self::STATUS_CANCELLED, 422, 'A cancelled bill cannot be reopened.');
            abort_if($locked->status === self::STATUS_OPEN, 422, 'This bill is already open.');

            $locked->update(['status' => self::STATUS_OPEN, 'settled_at' => null, 'settled_by_user_id' => null]);
            $this->setRawAttributes($locked->getAttributes());
        });
    }

    /**
     * Cancel a bill entirely (e.g. the school withdrew from the event before ordering was
     * finalized) — a terminal state, same as FestRegistration's withdrawn. Locked for the
     * same stale-read reason as settle(): an unlocked read of amount_paid could pass this
     * guard just before a concurrent payment commits.
     */
    public function cancel(): void
    {
        DB::transaction(function () {
            $locked = static::whereKey($this->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status === self::STATUS_CANCELLED, 422, 'Bill is already cancelled.');
            abort_if($locked->amount_paid > 0, 422, 'This bill has payments recorded — void the payment(s) first so the refund is explicit, then cancel.');

            $locked->update(['status' => self::STATUS_CANCELLED]);
            $this->setRawAttributes($locked->getAttributes());
        });
    }
}
