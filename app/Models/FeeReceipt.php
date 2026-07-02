<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FeeReceipt extends Model
{
    protected $fillable = [
        'feeable_type', 'feeable_id', 'receipt_number', 'file_path', 'generated_receipt_path', 'transaction_ref', 'bank_name',
        'payment_date', 'amount', 'status', 'rejection_reason',
        'uploaded_by_user_id', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'payment_date' => 'date',
        'reviewed_at'  => 'datetime',
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
}
