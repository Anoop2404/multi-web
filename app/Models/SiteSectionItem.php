<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteSectionItem extends Model
{
    protected $fillable = [
        'tenant_id',
        'site_id',
        'site_section_id',
        'item_key',
        'sort_order',
        'display_order',
        'data',
        'meta',
        'visible_from',
        'visible_until',
        'is_enabled',
        'is_featured',
    ];

    protected $casts = [
        'data' => 'array',
        'meta' => 'array',
        'visible_from' => 'datetime',
        'visible_until' => 'datetime',
        'is_enabled' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'display_order' => 'integer',
    ];

    public function siteSection(): BelongsTo
    {
        return $this->belongsTo(SiteSection::class, 'site_section_id');
    }

    public function scopeForRepeater($query, string $itemKey)
    {
        return $query->where('item_key', $itemKey)->orderBy('sort_order');
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true)
            ->where(function ($q) {
                $q->whereNull('visible_from')->orWhere('visible_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('visible_until')->orWhere('visible_until', '>=', now());
            });
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }
}
