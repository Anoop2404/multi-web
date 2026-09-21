<?php

namespace App\Support;

use App\Models\Tenant;

class SiteSectionMedia
{
    /**
     * Resolve every schema-defined media value in a section config to a browser URL.
     *
     * Section configs intentionally store portable paths rather than expiring S3 URLs.
     * This keeps those paths portable while ensuring every Blade variant receives a
     * display-ready URL, including media nested inside repeaters such as hero slides.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function resolveConfig(
        Tenant $tenant,
        string $sectionType,
        string $variant,
        array $config
    ): array {
        return self::resolveFields(
            $tenant,
            SectionFieldRegistry::fields($sectionType, $variant),
            $config
        );
    }

    /**
     * Build a path => URL map used by the CMS to preview existing uploads.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    public static function urlMap(
        Tenant $tenant,
        string $sectionType,
        string $variant,
        array $config
    ): array {
        $urls = [];
        self::collectUrls(
            $tenant,
            SectionFieldRegistry::fields($sectionType, $variant),
            $config,
            $urls
        );

        return $urls;
    }

    /** @param array<int, array<string, mixed>> $fields */
    private static function resolveFields(Tenant $tenant, array $fields, array $values): array
    {
        foreach ($fields as $field) {
            $key = $field['key'] ?? null;
            if (! is_string($key) || ! array_key_exists($key, $values)) {
                continue;
            }

            if (($field['type'] ?? null) === 'media' && is_string($values[$key])) {
                $values[$key] = TenantStorage::siteMediaUrl($tenant, $values[$key]);

                continue;
            }

            if (($field['type'] ?? null) !== 'repeater' || ! is_array($values[$key])) {
                continue;
            }

            $childFields = is_array($field['fields'] ?? null) ? $field['fields'] : [];
            $values[$key] = array_map(
                fn ($item) => is_array($item)
                    ? self::resolveFields($tenant, $childFields, $item)
                    : $item,
                $values[$key]
            );
        }

        return $values;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $urls
     */
    private static function collectUrls(Tenant $tenant, array $fields, array $values, array &$urls): void
    {
        foreach ($fields as $field) {
            $key = $field['key'] ?? null;
            if (! is_string($key) || ! array_key_exists($key, $values)) {
                continue;
            }

            if (($field['type'] ?? null) === 'media' && is_string($values[$key]) && $values[$key] !== '') {
                $resolved = TenantStorage::siteMediaUrl($tenant, $values[$key]);
                if ($resolved) {
                    $urls[$values[$key]] = $resolved;
                }

                continue;
            }

            if (($field['type'] ?? null) !== 'repeater' || ! is_array($values[$key])) {
                continue;
            }

            $childFields = is_array($field['fields'] ?? null) ? $field['fields'] : [];
            foreach ($values[$key] as $item) {
                if (is_array($item)) {
                    self::collectUrls($tenant, $childFields, $item, $urls);
                }
            }
        }
    }
}
