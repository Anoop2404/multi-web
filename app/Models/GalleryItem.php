<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use App\Support\TenantStorage;
use Illuminate\Database\Eloquent\Model;

class GalleryItem extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = ['album_id', 'tenant_id', 'image_path', 'caption', 'display_order'];

    protected $appends = ['image_url'];

    public function album()
    {
        return $this->belongsTo(GalleryAlbum::class);
    }

    public function tenant()
    {
        return $this->belongsToCentralTenant();
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        $tenant = optional(tenancy())->tenant;
        if (! $tenant || $tenant->id !== $this->tenant_id) {
            $tenant = Tenant::find($this->tenant_id);
        }

        return TenantStorage::assetUrl($tenant, $this->image_path, route('tenant.gallery.photo', $this, false));
    }
}
