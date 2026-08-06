<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * A payment recorded against a FestFoodBill. Multiple rows are expected — a school may pay
 * in advance (prepaid) and/or have cash collected at the counter over several days; each
 * just adds another row rather than the bill being a single pay/unpaid toggle.
 */
class FestFoodPayment extends Model
{
    protected $fillable = [
        'bill_id', 'amount', 'payment_mode', 'receipt_number',
        'received_by_user_id', 'received_at', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'received_at' => 'datetime',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(FestFoodBill::class, 'bill_id');
    }

    /** Same atomic-lock pattern as FestFoodCoupon::generateCode(). */
    public static function generateReceiptNumber(FestFoodBill $bill): string
    {
        return DB::transaction(function () use ($bill) {
            FestFoodBill::whereKey($bill->id)->lockForUpdate()->first();

            $n = static::where('bill_id', $bill->id)->count() + 1;

            return 'FB'.$bill->id.'-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
        });
    }
}
