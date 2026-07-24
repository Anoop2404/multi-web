<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A credit owed to a school after a paid MCQ or Training registration was cancelled.
 * Mirrors FestFeeCredit (which lives on fest_fee_credits) but is polymorphic so
 * McqSchoolFee and TrainingSchoolFee can both be creditable types.
 *
 * See docs/FLOW_GAP_FIX_PLAN.md Phase 1.1.
 */
class ProgramFeeCredit extends Model
{
    protected $fillable = [
        'creditable_type', 'creditable_id',
        'source_type', 'source_id',
        'amount', 'reason',
        'created_by_user_id', 'applied_at',
        'credit_note_number', 'generated_note_path',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'applied_at' => 'datetime',
    ];

    /**
     * The fee aggregate record this credit is posted against
     * (McqSchoolFee | TrainingSchoolFee).
     */
    public function creditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The cancelled registration that triggered this credit
     * (McqRegistration | TrainingRegistration).
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
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
