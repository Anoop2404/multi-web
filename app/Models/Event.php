<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use App\Support\TenantStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Event extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = ['tenant_id', 'title', 'slug', 'description', 'image', 'start_date', 'end_date', 'venue', 'is_upcoming'];

    protected $casts = ['is_upcoming' => 'boolean', 'start_date' => 'date', 'end_date' => 'date'];

    protected $appends = ['image_url'];

    public function tenant()
    {
        return $this->belongsToCentralTenant();
    }

    public function scopeUpcoming($q)
    {
        return $q->where('start_date', '>=', now()->toDateString())->orderBy('start_date');
    }

    public function scopePast($q)
    {
        return $q->where('start_date', '<', now()->toDateString())->orderByDesc('start_date');
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        $tenant = optional(tenancy())->tenant;
        if (! $tenant || $tenant->id !== $this->tenant_id) {
            $tenant = Tenant::find($this->tenant_id);
        }

        return TenantStorage::assetUrl($tenant, $this->image, route('tenant.events.image', $this, false));
    }

    public static function boot()
    {
        parent::boot();
        static::creating(fn ($m) => $m->slug = $m->slug ?? Str::slug($m->title));
        static::saved(fn (self $model) => $model->invalidateTenantCache());
        static::deleted(fn (self $model) => $model->invalidateTenantCache());
    }

    public function invalidateTenantCache(): void
    {
        if ($tenant = Tenant::find($this->tenant_id)) {
            $tenant->invalidateCache();
        }
    }
}
