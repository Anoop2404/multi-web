<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountHead extends Model
{
    protected $fillable = ["tenant_id", "financial_year_id", "code", "name", "type", "category", "event_id", "is_active"];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(LedgerTransaction::class, 'account_head_id');
    }
}
