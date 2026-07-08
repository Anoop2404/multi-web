<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;

class TenantSetting extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = ['tenant_id', 'key', 'value'];

    protected $casts = [
        'value' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsToCentralTenant();
    }
}
