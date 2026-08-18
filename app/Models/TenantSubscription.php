<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class TenantSubscription extends Model
{
    use CentralConnection;

    protected $fillable = [
        'tenant_id', 'plan_id', 'period_start', 'period_end',
        'status', 'auto_renew', 'suspended_at', 'suspended_reason',
    ];

    protected $casts = [
        'period_start'  => 'date',
        'period_end'    => 'date',
        'auto_renew'    => 'boolean',
        'suspended_at'  => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInGrace(): bool
    {
        return $this->status === 'grace';
    }

    public function isReadOnly(): bool
    {
        return $this->status === 'readonly';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }
}
