<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsArticle extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = ['tenant_id','title','slug','body','image','category','is_featured','published_at'];
    protected $casts = ['is_featured' => 'boolean', 'published_at' => 'datetime'];

    public function tenant() { return $this->belongsToCentralTenant(); }
    public function scopePublished($q) { return $q->whereNotNull('published_at')->where('published_at','<=',now()); }
    public function scopeFeatured($q) { return $q->where('is_featured', true); }
    public static function boot() {
        parent::boot();
        static::creating(fn($m) => $m->slug = $m->slug ?? Str::slug($m->title));
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
