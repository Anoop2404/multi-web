<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use App\Support\TenantStorage;
use Illuminate\Database\Eloquent\Model;

class Alumni extends Model
{
    use BelongsToCentralTenant;

    protected $table = 'alumni';

    protected $fillable = ['tenant_id', 'name', 'batch_year', 'current_role', 'current_organisation', 'photo', 'message', 'email', 'is_featured', 'is_approved'];

    protected $casts = ['is_featured' => 'boolean', 'is_approved' => 'boolean'];

    protected $appends = ['photo_url'];

    public function tenant()
    {
        return $this->belongsToCentralTenant();
    }

    public function scopeApproved($q)
    {
        return $q->where('is_approved', true);
    }

    public function scopeFeatured($q)
    {
        return $q->where('is_featured', true);
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
