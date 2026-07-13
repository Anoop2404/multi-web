<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteSection extends Model
{
    use BelongsToCentralTenant;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'tenant_id',
        'site_id',
        'section_type',
        'variant',
        'display_order',
        'is_active',
        'status',
        'config',
        'published_config',
        'published_at',
        'updated_by',
        'archived_configs',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
        'published_config' => 'array',
        'archived_configs' => 'array',
        'display_order' => 'integer',
        'published_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(WebsiteSite::class, 'site_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SiteSectionVersion::class, 'site_section_id')->orderByDesc('id');
    }

    public function archiveCurrentConfig(): void
    {
        if (empty($this->config)) {
            return;
        }

        $history = $this->archived_configs ?? [];
        $history[] = [
            'variant' => $this->variant,
            'config' => $this->config,
            'archived_at' => now()->toIso8601String(),
        ];

        // Cap variant archives
        if (count($history) > 20) {
            $history = array_slice($history, -20);
        }

        $this->archived_configs = $history;
    }

    public function recordVersion(?string $note = null, ?int $userId = null): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('site_section_versions')) {
            return;
        }

        SiteSectionVersion::create([
            'site_section_id' => $this->id,
            'variant' => $this->variant,
            'config' => $this->config,
            'note' => $note,
            'created_by' => $userId ?? auth()->id(),
            'created_at' => now(),
        ]);

        // Keep last 50 versions
        $oldIds = SiteSectionVersion::where('site_section_id', $this->id)
            ->orderByDesc('id')
            ->skip(50)
            ->take(100)
            ->pluck('id');
        if ($oldIds->isNotEmpty()) {
            SiteSectionVersion::whereIn('id', $oldIds)->delete();
        }
    }

    public function publish(?int $userId = null): void
    {
        $this->published_config = $this->config;
        $this->status = self::STATUS_PUBLISHED;
        $this->published_at = now();
        $this->updated_by = $userId ?? auth()->id();
        $this->save();
        $this->recordVersion('Published', $userId);
    }

    /** Config the public site should render. */
    public function publicConfig(): array
    {
        if ($this->published_config !== null) {
            return $this->published_config;
        }

        // Legacy rows before FRD-20 migration
        return $this->config ?? [];
    }

    public function hasUnpublishedChanges(): bool
    {
        if ($this->status === self::STATUS_DRAFT) {
            return true;
        }

        return json_encode($this->config ?? []) !== json_encode($this->published_config ?? []);
    }

    public function tenant()
    {
        return $this->belongsToCentralTenant();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('display_order');
    }

    public function scopePublished($query)
    {
        return $query->where(function ($q) {
            $q->where('status', self::STATUS_PUBLISHED)
                ->orWhereNull('status');
        });
    }

    public function scopeForPublic($query)
    {
        return $query->active()->published();
    }
}
