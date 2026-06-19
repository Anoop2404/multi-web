<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSection extends Model
{
    protected $fillable = [
        'tenant_id',
        'section_type',
        'variant',
        'display_order',
        'is_active',
        'config',
        'archived_configs',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'config'          => 'array',
        'archived_configs'=> 'array',
        'display_order'   => 'integer',
    ];

    /**
     * Archive the current config before switching to a new variant.
     */
    public function archiveCurrentConfig(): void
    {
        if (empty($this->config)) {
            return;
        }

        $history = $this->archived_configs ?? [];
        $history[] = [
            'variant'     => $this->variant,
            'config'      => $this->config,
            'archived_at' => now()->toIso8601String(),
        ];

        $this->archived_configs = $history;
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('display_order');
    }
}
