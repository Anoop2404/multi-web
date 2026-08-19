<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WebsiteSite extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'is_primary', 'is_active', 'seo_json',
        'template_key', 'template_version', 'experience_version', 'homepage_mode',
        'homepage_mode_override_until', 'design_json', 'draft_template_json',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'seo_json' => 'array',
        'design_json' => 'array',
        'draft_template_json' => 'array',
        'homepage_mode_override_until' => 'datetime',
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(SiteSection::class, 'site_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WebsiteSiteVersion::class, 'website_site_id')->orderByDesc('id');
    }

    /**
     * Sections belonging to this site. The primary site also owns legacy rows
     * whose site_id predates the multi-site migration and is therefore null —
     * but only as a fallback while the primary site has no sections of its own;
     * once real site_id-scoped sections exist, the legacy rows are excluded so
     * they don't get interleaved with (and duplicate) real content.
     */
    public function sectionQuery(): Builder
    {
        $fallsBackToLegacy = $this->is_primary && ! $this->sections()->exists();

        return SiteSection::query()
            ->where('tenant_id', $this->tenant_id)
            ->where(function (Builder $query) use ($fallsBackToLegacy) {
                $query->where('site_id', $this->id);

                if ($fallsBackToLegacy) {
                    $query->orWhereNull('site_id');
                }
            });
    }

    public static function resolveForTenant(string $tenantId, ?int $siteId = null): self
    {
        if ($siteId !== null) {
            return self::query()
                ->where('tenant_id', $tenantId)
                ->findOrFail($siteId);
        }

        return self::ensurePrimary($tenantId);
    }

    public static function ensurePrimary(string $tenantId): self
    {
        $existing = self::where('tenant_id', $tenantId)->where('is_primary', true)->first();
        if ($existing) {
            return $existing;
        }

        return self::create([
            'tenant_id' => $tenantId,
            'name' => 'Main website',
            'slug' => 'main',
            'is_primary' => true,
            'is_active' => true,
            'seo_json' => [],
        ]);
    }

    public static function uniqueSlug(string $tenantId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'site';
        $candidate = $base;
        $n = 2;
        while (
            self::where('tenant_id', $tenantId)
                ->where('slug', $candidate)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $candidate = $base.'-'.$n++;
        }

        return $candidate;
    }
}
