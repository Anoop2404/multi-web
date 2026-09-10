<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use App\Support\TenantStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GalleryAlbum extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = ['tenant_id', 'title', 'slug', 'description', 'cover_image', 'display_order'];

    protected $appends = ['cover_url'];

    public function tenant()
    {
        return $this->belongsToCentralTenant();
    }

    public function items()
    {
        return $this->hasMany(GalleryItem::class, 'album_id')->orderBy('display_order');
    }

    public function getCoverUrlAttribute(): ?string
    {
        if (! $this->cover_image) {
            return null;
        }

        $tenant = optional(tenancy())->tenant;
        if (! $tenant || $tenant->id !== $this->tenant_id) {
            $tenant = Tenant::find($this->tenant_id);
        }

        return TenantStorage::assetUrl($tenant, $this->cover_image, route('tenant.gallery.cover', $this, false));
    }

    public static function boot()
    {
        parent::boot();
        static::creating(fn ($m) => $m->slug = $m->slug ?? Str::slug($m->title));
    }
}
