<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FeeReceipt extends Model
{
    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUS_REVERSED = 'reversed';

    /** Ledger reference_type for compensating reversal journals (not FeeReceipt::class). */
    public const REVERSAL_REFERENCE = 'fee_receipt_reversal';

    protected $fillable = [
        'feeable_type', 'feeable_id', 'receipt_number', 'file_path', 'generated_receipt_path', 'transaction_ref', 'bank_name',
        'payment_date', 'amount', 'waiver_amount', 'waiver_reason', 'waived_by_user_id', 'status', 'rejection_reason',
        'rejection_history',
        'uploaded_by_user_id', 'reviewed_by', 'reviewed_at',
        'reversed_by', 'reversed_at', 'reversal_reason',
        'receipt_emailed_at', 'receipt_email_status', 'receipt_email_error', 'receipt_email_resend_count',
        'is_system_credit',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'waiver_amount'=> 'decimal:2',
        'payment_date' => 'date',
        'reviewed_at'  => 'datetime',
        'reversed_at'  => 'datetime',
        'receipt_emailed_at' => 'datetime',
        'rejection_history' => 'array',
        'is_system_credit' => 'boolean',
    ];

    public function feeable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /** Additional proof images beyond the primary `file_path` — see FeeReceiptAttachment. */
    public function attachments(): HasMany
    {
        return $this->hasMany(FeeReceiptAttachment::class)->orderBy('sort_order');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reversedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isSuperseded(): bool
    {
        return $this->status === self::STATUS_SUPERSEDED;
    }

    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }

    /**
     * True for a system-generated "receipt" created when an outstanding FestFeeCredit is
     * auto-applied against a new balance (FestSchoolEventFeeService::applyAvailableCredit()) —
     * carries a placeholder file_path, not a real uploaded proof. Callers that serve/download
     * `file_path` (e.g. FestSchoolEventFeeController::proof()) must check this first.
     */
    public function isSystemCredit(): bool
    {
        return (bool) $this->is_system_credit;
    }

    /** Append a rejection event to the history array, mirroring BoardResult::correction_history. */
    public function appendRejectionHistory(string $reason, ?int $userId): array
    {
        $history = $this->rejection_history ?? [];
        $history[] = [
            'at'     => now()->toIso8601String(),
            'by'     => $userId,
            'reason' => $reason,
        ];

        return $history;
    }

    /** Mark prior uploaded/rejected proofs inactive when a school re-uploads. */
    public static function supersedePriorForFeeable(Model $feeable): void
    {
        static::query()
            ->where('feeable_type', $feeable->getMorphClass())
            ->where('feeable_id', $feeable->getKey())
            ->whereIn('status', ['uploaded', 'rejected'])
            ->update(['status' => 'superseded']);
    }
}
