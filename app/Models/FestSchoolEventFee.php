<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use App\Models\Concerns\TracksPartialPayments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class FestSchoolEventFee extends Model
{
    use BelongsToCentralTenant;
    use TracksPartialPayments;

    protected $fillable = [
        'event_id', 'school_id', 'head_id', 'school_registration_fee', 'student_registration_fee',
        'participation_item_count', 'participation_fee', 'extra_item_fee', 'total_due',
        'amount_paid', 'override_amount', 'fee_receipt_id', 'status',
    ];

    protected $casts = [
        'school_registration_fee' => 'decimal:2',
        'student_registration_fee' => 'decimal:2',
        'participation_fee' => 'decimal:2',
        'extra_item_fee' => 'decimal:2',
        'total_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'override_amount' => 'decimal:2',
    ];

    /**
     * When an event has per-head fee rows, exclude the head_id-null rollup so
     * sum(total_due) does not double-count sports_composite (and similar) billing.
     */
    public function scopeForAmountAggregation(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->where(function (Builder $inner) use ($table) {
            $inner->whereNotNull("{$table}.head_id")
                ->orWhereNotExists(function ($sub) use ($table) {
                    $sub->selectRaw('1')
                        ->from("{$table} as head_fees")
                        ->whereColumn('head_fees.event_id', "{$table}.event_id")
                        ->whereNotNull('head_fees.head_id');
                });
        });
    }

    /** @param  Collection<int, self>  $fees */
    public static function withoutDuplicateRollups(Collection $fees): Collection
    {
        $eventsWithHeads = $fees->whereNotNull('head_id')->pluck('event_id')->unique();

        return $fees->filter(function (self $fee) use ($eventsWithHeads) {
            if ($fee->head_id !== null) {
                return true;
            }

            return ! $eventsWithHeads->contains($fee->event_id);
        })->values();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(FestEvent::class, 'event_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsToCentralTenant('school_id');
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(FestItemHead::class, 'head_id');
    }

    public function feeReceipt(): BelongsTo
    {
        return $this->belongsTo(FeeReceipt::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FestSchoolEventFeeLine::class, 'fest_school_event_fee_id');
    }

    public function credits(): HasMany
    {
        return $this->hasMany(FestFeeCredit::class, 'fest_school_event_fee_id');
    }

    /** Sum of credits owed to the school that haven't been applied to a fee yet. */
    public function outstandingCredit(): float
    {
        return (float) $this->credits()->outstanding()->sum('amount');
    }

    /**
     * outstandingBalance() minus available credit — informational only, e.g. "you owe ₹X
     * after credit" messaging. Deliberately does NOT feed back into outstandingBalance()
     * itself, isFullyPaid(), or refreshPaidState(): refreshPaidState() (see
     * Concerns\TracksPartialPayments) recomputes amount_paid purely from the sum of approved
     * FeeReceipt rows every time it runs, so anything that tried to fold credit into
     * amount_paid directly would just get silently overwritten on the next recalculate().
     * See docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §13 for the full reasoning and what
     * actually consumes a credit (FestSchoolEventFeeService::markCreditsApplied(), wired into
     * the existing forceApprove waiver action rather than fighting refreshPaidState()).
     */
    public function effectiveOutstandingBalance(): float
    {
        return round(max(0, $this->outstandingBalance() - $this->outstandingCredit()), 2);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
