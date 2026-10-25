<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceDocument extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['amount' => 'decimal:2', 'document_date' => 'date', 'next_due' => 'date', 'recurring' => 'boolean'];

    public function vendor()
    {
        return $this->belongsTo(FinanceVendor::class);
    }

    public function accountHead()
    {
        return $this->belongsTo(AccountHead::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }
}
