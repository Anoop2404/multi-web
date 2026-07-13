<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WebsiteSite extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'is_primary', 'is_active', 'seo_json',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'seo_json' => 'array',
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(SiteSection::class, 'site_id');
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
