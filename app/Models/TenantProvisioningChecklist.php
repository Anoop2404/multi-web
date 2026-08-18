<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class TenantProvisioningChecklist extends Model
{
    use CentralConnection;

    protected $fillable = ['tenant_id', 'step_key', 'completed_at', 'completed_by_user_id'];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
