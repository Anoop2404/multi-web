<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FeeReceipt extends Model
{
    protected $fillable = [
        'feeable_type', 'feeable_id', 'receipt_number', 'file_path', 'generated_receipt_path', 'transaction_ref', 'bank_name',
        'payment_date', 'amount', 'waiver_amount', 'waiver_reason', 'waived_by_user_id', 'status', 'rejection_reason',
        'uploaded_by_user_id', 'reviewed_by', 'reviewed_at',
        'receipt_emailed_at', 'receipt_email_status', 'receipt_email_error', 'receipt_email_resend_count',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'waiver_amount'=> 'decimal:2',
        'payment_date' => 'date',
        'reviewed_at'  => 'datetime',
        'receipt_emailed_at' => 'datetime',
    ];

    public function feeable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isSuperseded(): bool
    {
        return $this->status === 'superseded';
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
