<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use App\Support\TenantStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsArticle extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = ['tenant_id', 'title', 'slug', 'body', 'image', 'category', 'is_featured', 'published_at'];

    protected $casts = ['is_featured' => 'boolean', 'published_at' => 'datetime'];

    protected $appends = ['image_url'];

    public function tenant()
    {
        return $this->belongsToCentralTenant();
    }

    public function scopePublished($q)
    {
        return $q->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function scopeFeatured($q)
    {
        return $q->where('is_featured', true);
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

        return TenantStorage::assetUrl($tenant, $this->image, route('tenant.news.image', $this, false));
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
