<?php

namespace App\Support;

use App\Models\FestEvent;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Services\Membership\EffectiveMasterDataResolver;

class FestClassGroupScheme
{
    public const CLUSTER_PREFIX = 'cc_';

    public const KEYS = ['lp', 'up', 'hs', 'hss', 'open'];

    /**
     * Convert legacy/imported category spellings to the built-in machine keys.
     * Unknown keys are preserved for custom and named class-group schemes.
     */
    public static function canonicalKey(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        $trimmed = trim($key);
        $compact = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $trimmed));

        return match ($compact) {
            'lp', 'category1', 'categoryi', 'cat1', 'cati', 'cc1' => 'lp',
            'up', 'category2', 'categoryii', 'cat2', 'catii', 'cc2' => 'up',
            'hs', 'category3', 'categoryiii', 'cat3', 'catiii', 'cc3' => 'hs',
            'hss', 'category4', 'categoryiv', 'cat4', 'cativ', 'cc4' => 'hss',
            'open', 'category5', 'categoryv', 'cat5', 'catv', 'cc5' => 'open',
            default => $trimmed,
        };
    }

    /**
     * Cached per tenant per request. resolve() is called once per student inside
     * FestRegistrationEligibilityService::annotateStudents()'s per-student loop
     * (and again per student in validateStudent()); without this cache the
     * SahodayaProfile lookup below re-runs on every call even though the result
     * is identical for every student in the same event/tenant.
     *
     * @var array<string, ?string>
     */
    private static array $profileSchemeCache = [];

    public static function options(): array
    {
        return config('fest_class_group_schemes.options', []);
    }

    public static function defaultScheme(): string
    {
        return config('fest_class_group_schemes.default', 'cbse');
    }

    public static function isValid(?string $scheme): bool
    {
        // Legacy string values — still recognized so events saved before named schemes
        // existed keep resolving. New saves from the fee-settings form now send a numeric
        // FestClassCategoryScheme id instead (see the branch below).
        if ($scheme === 'cluster' || $scheme === 'custom') {
            return true;
        }

        if (is_string($scheme) && ctype_digit($scheme)) {
            return true;
        }

        return filled($scheme) && isset(config('fest_class_group_schemes.schemes')[$scheme]);
    }

    public static function resolve(?string $scheme = null, ?FestEvent $event = null, ?Tenant $sahodaya = null): string
    {
        if ($event) {
            $eventScheme = $event->fee_settings['class_group_scheme'] ?? null;
            if (self::isValid($eventScheme)) {
                return $eventScheme;
            }
        }

        if (self::isValid($scheme)) {
            return $scheme;
        }

        $tenantId = $sahodaya?->id ?? $event?->tenant_id;
        if ($tenantId) {
            try {
                // array_key_exists (not ??=) so a genuinely-null result (no profile
                // row, or the column unset) is cached too — otherwise every tenant
                // without this field set would re-query on every single call.
                if (! array_key_exists($tenantId, self::$profileSchemeCache)) {
                    self::$profileSchemeCache[$tenantId] = SahodayaProfile::where('tenant_id', $tenantId)
                        ->value('fest_class_group_scheme');
                }
                $profileScheme = self::$profileSchemeCache[$tenantId];
                if (self::isValid($profileScheme)) {
                    return $profileScheme;
                }
            } catch (\Throwable) {
                // Column may be missing on older tenant databases — fall back to default scheme.
            }
        }

        return self::defaultScheme();
    }

    public static function resolveForEvent(FestEvent $event, ?array $schedule = null): string
    {
        $scheduleScheme = $schedule['class_group_scheme'] ?? null;

        return self::resolve($scheduleScheme, $event);
    }

    /** @return array<string, string> */
    public static function labels(?string $scheme = null, ?FestEvent $event = null): array
    {
        $resolved = self::resolve($scheme, $event);

        if (is_string($resolved) && ctype_digit($resolved)) {
            return self::labelsForSchemeId((int) $resolved);
        }

        if ($resolved === 'custom') {
            return $event ? self::customLabelsForEvent($event) : ['open' => 'Open / All Categories'];
        }

        if ($resolved === 'cluster') {
            $tenantId = $event?->tenant_id ?? null;

            return $tenantId ? self::clusterLabels($tenantId) : ['open' => 'Open / All Categories'];
        }

        return config("fest_class_group_schemes.schemes.{$resolved}.groups", []);
    }

    /**
     * Labels for a named FestClassCategoryScheme, keyed the same way as every other
     * scheme (machine key => display label), always including the universal 'open'
     * catch-all. A deleted/missing scheme id resolves to just the 'open' bucket rather
     * than erroring, so a stale reference degrades gracefully instead of breaking the page.
     *
     * @return array<string, string>
     */
    /**
     * A fresh admin-created scheme/category group (Category Masters' own "Display label"
     * field) expects just a short name like "Junior" and relies on classesSuffix() to
     * append the class range. But FestClassCategoryScheme::ensureDefaultsForTenant()'s 3
     * auto-seeded starter schemes ("State Kalotsav", "CBSE Kerala", "Sahodaya standard")
     * store the FULL descriptive text in `label` already (e.g. "Category 1 — Classes 3 &
     * 4") — appending unconditionally doubled the class range for every one of those.
     * Shared by labelsForSchemeId() and customLabelsForEvent(), the two callers that read
     * an admin-entered `label` alongside a `classes` array.
     */
    private static function appendClassesSuffixOnce(object $row): string
    {
        $suffix = $row->classesSuffix();

        return str_ends_with($row->label, $suffix) ? $row->label : $row->label.$suffix;
    }

    public static function labelsForSchemeId(int $schemeId): array
    {
        $labels = ['open' => 'Open / All Categories'];

        $rows = \App\Models\FestClassCategorySchemeGroup::where('scheme_id', $schemeId)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['key', 'label', 'classes']);

        foreach ($rows as $row) {
            $labels[$row->key] = self::appendClassesSuffixOnce($row);
        }

        return $labels;
    }

    /** @return array<string, float> */
    public static function defaultFees(?string $scheme = null, ?FestEvent $event = null): array
    {
        $resolved = self::resolve($scheme, $event);

        // Named/custom event categories carry no built-in fee defaults — this scheme is
        // deliberately fee-agnostic (see fest_school_event_fee.custom class_group fee entry,
        // which is set per event via the item catalog / class_group_fees form like any other
        // scheme).
        if ($resolved === 'custom' || $resolved === 'cluster' || (is_string($resolved) && ctype_digit($resolved))) {
            return [];
        }

        return config("fest_class_group_schemes.schemes.{$resolved}.default_fees", []);
    }

    /**
     * Custom per-event category labels, keyed the same way as every other scheme
     * (machine key => display label), always including the universal 'open' catch-all
     * so items without a specific category still resolve to something.
     *
     * @return array<string, string>
     */
    public static function customLabelsForEvent(FestEvent $event): array
    {
        $labels = ['open' => 'Open / All Categories'];

        $rows = \App\Models\FestEventClassGroup::where('event_id', $event->id)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['key', 'label', 'classes']);

        foreach ($rows as $row) {
            $labels[$row->key] = self::appendClassesSuffixOnce($row);
        }

        return $labels;
    }

    /** @return array<string, string> */
    public static function labelsForSahodaya(?string $sahodayaTenantId): array
    {
        if (! $sahodayaTenantId) {
            return self::labels();
        }

        $scheme = SahodayaProfile::where('tenant_id', $sahodayaTenantId)->value('fest_class_group_scheme');

        if ($scheme === 'cluster') {
            return self::clusterLabels($sahodayaTenantId);
        }

        return self::labels($scheme);
    }

    /** @return array<string, string> */
    public static function clusterLabels(string $sahodayaId): array
    {
        $resolver = app(EffectiveMasterDataResolver::class);
        $categories = $resolver->classCategories($sahodayaId);
        $classesByCategory = $resolver->masterClasses($sahodayaId)->groupBy('class_category_id');

        $labels = ['open' => 'Open / All Categories'];

        foreach ($categories as $category) {
            $classNames = ($classesByCategory[$category->id] ?? collect())
                ->pluck('name')
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->sort()
                ->values()
                ->all();

            $suffix = $classNames !== []
                ? ' — Classes '.implode(', ', $classNames)
                : '';

            $labels[self::clusterKey((int) $category->id)] = trim($category->label.$suffix);
        }

        return $labels;
    }

    public static function clusterKey(int $classCategoryId): string
    {
        return self::CLUSTER_PREFIX.$classCategoryId;
    }

    public static function isClusterKey(?string $key): bool
    {
        return is_string($key) && str_starts_with($key, self::CLUSTER_PREFIX);
    }

    public static function categoryIdFromClusterKey(?string $key): ?int
    {
        if (! self::isClusterKey($key)) {
            return null;
        }

        $id = (int) substr($key, strlen(self::CLUSTER_PREFIX));

        return $id > 0 ? $id : null;
    }

    /** @return array<string, string> */
    public static function taxonomyClassGroups(?string $scheme = null, ?FestEvent $event = null): array
    {
        return self::labels($scheme, $event);
    }
}
