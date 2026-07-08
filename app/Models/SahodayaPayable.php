<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SahodayaPayable extends Model
{
    protected $fillable = [
        'tenant_id', 'financial_year_id', 'vendor_name', 'description',
        'amount', 'amount_paid', 'due_date', 'incurred_date', 'status',
        'expense_head_id', 'obligation_journal_id', 'payment_journal_id',
        'paid_at', 'created_by',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'amount_paid'  => 'decimal:2',
        'due_date'     => 'date',
        'incurred_date'=> 'date',
        'paid_at'      => 'datetime',
    ];

    public function expenseHead(): BelongsTo
    {
        return $this->belongsTo(AccountHead::class, 'expense_head_id');
    }

    public function balanceDue(): float
    {
        return max(0, (float) $this->amount - (float) $this->amount_paid);
    }
}
