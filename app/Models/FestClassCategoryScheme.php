<?php

namespace App\Models;

use App\Services\Membership\EffectiveMasterDataResolver;
use App\Support\FestStudentClassResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named, Sahodaya-wide class category setup (e.g. "CBSE Kerala (Category I-IV)",
 * "English Fest") — replaces the old fixed cbse/sahodaya/cluster/custom scheme choices.
 * Events reference one of these by id in fee_settings.class_group_scheme. See
 * App\Support\FestClassGroupScheme, which resolves that reference into category labels.
 */
class FestClassCategoryScheme extends Model
{
    protected $fillable = ['tenant_id', 'name', 'description', 'is_default', 'sort_order'];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function groups(): HasMany
    {
        return $this->hasMany(FestClassCategorySchemeGroup::class, 'scheme_id')
            ->orderBy('sort_order')
            ->orderBy('label');
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('fest_class_category_schemes.tenant_id', $tenantId);
    }

    /**
     * Seed the starter named schemes exactly once per tenant, snapshotting them from the
     * config-driven presets and live Class Master data that this feature replaces. Idempotent
     * — a no-op once any scheme already exists for the tenant, so this is safe to call on
     * every page load (see FestEventSettingsController::settings()) rather than needing a
     * one-off data migration script run per tenant database.
     */
    public static function ensureDefaultsForTenant(string $tenantId): void
    {
        if (self::forTenant($tenantId)->exists()) {
            return;
        }

        $sahodayaDefaultKey = SahodayaProfile::where('tenant_id', $tenantId)->value('fest_class_group_scheme') ?: 'cbse';

        $legacyClassNumbers = [
            'cbse' => ['lp' => [3, 4], 'up' => [5, 6, 7], 'hs' => [8, 9, 10], 'hss' => [11, 12]],
            'sahodaya' => ['lp' => [1, 2, 3, 4], 'up' => [5, 6, 7], 'hs' => [8, 9, 10], 'hss' => [11, 12]],
        ];

        $starters = [
            'cbse' => 'CBSE Kerala (Category I–IV)',
            'sahodaya' => 'Sahodaya standard (LP–HSS)',
        ];

        $sortOrder = 0;
        foreach ($starters as $key => $name) {
            $scheme = self::create([
                'tenant_id' => $tenantId,
                'name' => $name,
                'is_default' => $sahodayaDefaultKey === $key,
                'sort_order' => $sortOrder++,
            ]);

            $groupSortOrder = 0;
            foreach (config("fest_class_group_schemes.schemes.{$key}.groups", []) as $groupKey => $label) {
                if ($groupKey === 'open') {
                    continue; // 'open' is always an implicit catch-all, never a stored row
                }

                $scheme->groups()->create([
                    'tenant_id' => $tenantId,
                    'key' => $groupKey,
                    'label' => $label,
                    'classes' => $legacyClassNumbers[$key][$groupKey] ?? [],
                    'sort_order' => $groupSortOrder++,
                ]);
            }
        }

        self::seedClusterSnapshot($tenantId, $sahodayaDefaultKey === 'cluster', $sortOrder);
    }

    /**
     * Snapshot the tenant's live Class Categories / Class Master setup (previously the
     * 'cluster' scheme) as a third starter scheme. Deliberately a one-time copy, not a live
     * link — editing it afterwards here no longer touches the shared Class Master data used
     * elsewhere in Membership settings, matching how the other two starters behave.
     */
    private static function seedClusterSnapshot(string $tenantId, bool $isDefault, int $sortOrder): void
    {
        $resolver = app(EffectiveMasterDataResolver::class);
        $categories = $resolver->classCategories($tenantId);

        if ($categories->isEmpty()) {
            return;
        }

        $classesByCategory = $resolver->masterClasses($tenantId)->groupBy('class_category_id');

        $scheme = self::create([
            'tenant_id' => $tenantId,
            'name' => 'Class Master (Cluster)',
            'description' => 'Snapshot of your Class Categories & Class Master setup at the time named category schemes were introduced. Edit independently here — changes here no longer affect Membership settings.',
            'is_default' => $isDefault,
            'sort_order' => $sortOrder,
        ]);

        $groupSortOrder = 0;
        foreach ($categories as $category) {
            $classNumbers = ($classesByCategory[$category->id] ?? collect())
                ->map(fn ($masterClass) => FestStudentClassResolver::classNumberFromName($masterClass->name))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $scheme->groups()->create([
                'tenant_id' => $tenantId,
                'key' => 'cc_'.$category->id,
                'label' => $category->label,
                'classes' => $classNumbers,
                'sort_order' => $groupSortOrder++,
            ]);
        }
    }
}
