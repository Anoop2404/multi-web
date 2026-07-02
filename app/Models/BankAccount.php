<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $fillable = [
        'tenant_id', 'account_name', 'bank_name', 'account_no', 'ifsc', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
