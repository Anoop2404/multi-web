<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An additional proof image for a FeeReceipt beyond its primary `file_path` — see the
 * `fee_receipt_attachments` migration for why this exists (multi-image upload feature).
 */
class FeeReceiptAttachment extends Model
{
    protected $fillable = [
        'fee_receipt_id', 'file_path', 'original_filename', 'sort_order',
    ];

    public function feeReceipt(): BelongsTo
    {
        return $this->belongsTo(FeeReceipt::class);
    }
}
