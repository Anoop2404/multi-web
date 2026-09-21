<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use App\Support\TenantStorage;
use Illuminate\Database\Eloquent\Model;

class StaffMember extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = ['tenant_id', 'name', 'designation', 'department', 'qualification', 'photo', 'type', 'display_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected $appends = ['photo_url'];

    public function tenant()
    {
        return $this->belongsToCentralTenant();
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)->orderBy('display_order');
    }

    public function scopeByType($q, string $type)
    {
        return $q->where('type', $type);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        $tenant = optional(tenancy())->tenant;
        if (! $tenant || $tenant->id !== $this->tenant_id) {
            $tenant = Tenant::find($this->tenant_id);
        }

        return $this->photo ? TenantStorage::siteMediaUrl($tenant, $this->photo) : null;
    }
}
