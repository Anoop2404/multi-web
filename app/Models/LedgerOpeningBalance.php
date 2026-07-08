<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerOpeningBalance extends Model
{
    protected $fillable = [
        'tenant_id', 'financial_year_id', 'account_head_id',
        'entry_type', 'amount', 'notes', 'journal_id', 'posted_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function accountHead(): BelongsTo
    {
        return $this->belongsTo(AccountHead::class, 'account_head_id');
    }
}
