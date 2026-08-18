<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class TenantFeatureOverride extends Model
{
    use CentralConnection;

    protected $fillable = ['tenant_id', 'feature_key', 'enabled', 'limit_value'];

    protected $casts = [
        'enabled' => 'boolean',
        'limit_value' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
