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
        if ($scheme === 'cluster') {
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

        if ($resolved === 'cluster') {
            $tenantId = $event?->tenant_id ?? null;

            return $tenantId ? self::clusterLabels($tenantId) : ['open' => 'Open / All Categories'];
        }

        return config("fest_class_group_schemes.schemes.{$resolved}.groups", []);
    }

    /** @return array<string, float> */
    public static function defaultFees(?string $scheme = null, ?FestEvent $event = null): array
    {
        $resolved = self::resolve($scheme, $event);

        if ($resolved === 'cluster') {
            return [];
        }

        return config("fest_class_group_schemes.schemes.{$resolved}.default_fees", []);
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
