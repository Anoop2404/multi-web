<?php

namespace App\Support;

use App\Models\FestEvent;
use App\Models\SahodayaProfile;
use App\Models\Tenant;

class FestClassGroupScheme
{
    public const KEYS = ['lp', 'up', 'hs', 'hss', 'open'];

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
                $profileScheme = SahodayaProfile::where('tenant_id', $tenantId)->value('fest_class_group_scheme');
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

        return config("fest_class_group_schemes.schemes.{$resolved}.groups", []);
    }

    /** @return array<string, float> */
    public static function defaultFees(?string $scheme = null, ?FestEvent $event = null): array
    {
        $resolved = self::resolve($scheme, $event);

        return config("fest_class_group_schemes.schemes.{$resolved}.default_fees", []);
    }

    /** @return array<string, string> */
    public static function labelsForSahodaya(?string $sahodayaTenantId): array
    {
        if (! $sahodayaTenantId) {
            return self::labels();
        }

        $scheme = SahodayaProfile::where('tenant_id', $sahodayaTenantId)->value('fest_class_group_scheme');

        return self::labels($scheme);
    }

    /** @return array<string, string> */
    public static function taxonomyClassGroups(?string $scheme = null, ?FestEvent $event = null): array
    {
        $labels = self::labels($scheme, $event);
        if ($labels !== []) {
            return $labels;
        }

        return config('fest_item_taxonomy.class_group', []);
    }
}
