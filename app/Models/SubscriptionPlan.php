<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class SubscriptionPlan extends Model
{
    use CentralConnection;

    protected $fillable = ['name', 'slug', 'price_inr', 'billing_period', 'features', 'is_active'];

    protected $casts = [
        'price_inr'  => 'decimal:2',
        'features'   => 'array',
        'is_active'  => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class, 'plan_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class, 'plan_id');
    }
}
