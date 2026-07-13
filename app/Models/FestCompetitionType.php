<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FestCompetitionType extends Model
{
    protected $fillable = [
        'tenant_id',
        'type_key',
        'label',
        'nav_slug',
        'route_prefix',
        'icon',
        'description',
        'is_singleton',
        'is_system',
        'sort_order',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'is_singleton' => 'boolean',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
