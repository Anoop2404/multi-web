<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerTransaction extends Model
{
    protected $fillable = [
        'tenant_id', 'journal_id', 'financial_year_id', 'account_head_id', 'reference_type', 'reference_id',
        'entry_type', 'amount', 'description', 'transaction_date', 'posted_by', 'bank_account_id', 'reconciled_at',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'transaction_date' => 'date',
        'reconciled_at'    => 'datetime',
    ];

    public function accountHead(): BelongsTo
    {
        return $this->belongsTo(AccountHead::class);
    }
}
