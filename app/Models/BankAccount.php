<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'tenant_id', 'account_name', 'bank_name', 'account_no', 'ifsc', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function tenant()
    {
        return $this->belongsToCentralTenant('tenant_id');
    }
}
