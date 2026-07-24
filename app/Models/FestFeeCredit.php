<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A credit owed back to a school after a paid registration was rejected — see
 * FestRegistrationBulkService::rejectMany() for where these are created and
 * docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §9.2 for the full design.
 */
class FestFeeCredit extends Model
{
    /**
     * Ledger reference_type for the "credit consumed" journal leg (FestFeeLedgerService::
     * postCreditConsumed()), kept distinct from FestFeeCredit::class (used for the "credit
     * issued" leg, postCreditIssued()) so both legs can be posted against the SAME credit
     * row's id without postJournal()'s reference_type/reference_id dedup treating the second
     * call as a duplicate of the first and silently skipping it.
     */
    public const CONSUMPTION_REFERENCE = 'fest_fee_credit_consumption';

    protected $fillable = [
        'fest_school_event_fee_id', 'source_registration_id', 'amount', 'reason',
        'created_by_user_id', 'applied_at', 'credit_note_number', 'generated_note_path',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'applied_at' => 'datetime',
    ];

    public function schoolEventFee(): BelongsTo
    {
        return $this->belongsTo(FestSchoolEventFee::class, 'fest_school_event_fee_id');
    }

    public function sourceRegistration(): BelongsTo
    {
        return $this->belongsTo(FestRegistration::class, 'source_registration_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeOutstanding($query)
    {
        return $query->whereNull('applied_at');
    }
}
