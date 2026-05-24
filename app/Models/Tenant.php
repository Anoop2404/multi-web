<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDomains;

    protected $fillable = [
        'id',
        'type',
        'name',
        'domain',
        'subdomain',
        'parent_id',
        'plan',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'data' => 'array',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'type',
            'name',
            'domain',
            'subdomain',
            'parent_id',
            'plan',
            'is_active',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(Tenant::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Tenant::class, 'parent_id');
    }

    public function settings()
    {
        return $this->hasMany(TenantSetting::class, 'tenant_id');
    }

    public function sections()
    {
        return $this->hasMany(SiteSection::class, 'tenant_id');
    }
}
