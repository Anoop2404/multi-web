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

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
