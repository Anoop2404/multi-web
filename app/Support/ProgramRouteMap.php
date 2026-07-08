<?php

namespace App\Support;

class ProgramRouteMap
{
    /** @var array<string, array{slug: string, label: string, event_type: string, icon: string}> */
    public const FEST_PROGRAMS = [
        'kalotsav' => [
            'slug'       => 'kalotsav',
            'label'      => 'Kalotsav',
            'event_type' => 'kalolsavam',
            'icon'       => 'star',
        ],
        'sports' => [
            'slug'       => 'sports-meet',
            'label'      => 'Sports Meet',
            'event_type' => 'sports',
            'icon'       => 'award',
        ],
        'kids-fest' => [
            'slug'       => 'kids-fest',
            'label'      => 'Kids Fest',
            'event_type' => 'kids_fest',
            'icon'       => 'users',
        ],
        'teacher-fest' => [
            'slug'       => 'teacher-fest',
            'label'      => 'Teacher Fest',
            'event_type' => 'teacher_fest',
            'icon'       => 'users',
        ],
        'english-fest' => [
            'slug'       => 'english-fest',
            'label'      => 'English Fest',
            'event_type' => 'english_fest',
            'icon'       => 'file-text',
        ],
        'science-fest' => [
            'slug'       => 'science-fest',
            'label'      => 'Science Fest',
            'event_type' => 'science_fest',
            'icon'       => 'layers',
        ],
        'custom' => [
            'slug'       => 'custom',
            'label'      => 'Custom Events',
            'event_type' => 'custom',
            'icon'       => 'calendar',
        ],
    ];

    public static function slugFromPrefix(string $prefix): string
    {
        return self::FEST_PROGRAMS[$prefix]['slug'] ?? $prefix;
    }

    public static function prefixFromSlug(string $slug): string
    {
        foreach (self::FEST_PROGRAMS as $prefix => $meta) {
            if ($meta['slug'] === $slug) {
                return $prefix;
            }
        }

        return $slug;
    }

    public static function eventTypeFromPrefix(string $prefix): ?string
    {
        return self::FEST_PROGRAMS[$prefix]['event_type'] ?? null;
    }

    public static function eventTypeFromSlug(string $slug): ?string
    {
        foreach (self::FEST_PROGRAMS as $meta) {
            if ($meta['slug'] === $slug) {
                return $meta['event_type'];
            }
        }

        return null;
    }

    public static function slugFromEventType(string $eventType): ?string
    {
        foreach (self::FEST_PROGRAMS as $meta) {
            if ($meta['event_type'] === $eventType) {
                return $meta['slug'];
            }
        }

        return null;
    }

    public static function labelForSlug(string $slug): string
    {
        foreach (self::FEST_PROGRAMS as $meta) {
            if ($meta['slug'] === $slug) {
                return $meta['label'];
            }
        }

        return ucfirst(str_replace('-', ' ', $slug));
    }

    /** @return list<string> */
    public static function festProgramSlugs(): array
    {
        return array_values(array_map(fn ($m) => $m['slug'], self::FEST_PROGRAMS));
    }

    /** @return list<string> */
    public static function festPrefixes(): array
    {
        return array_keys(self::FEST_PROGRAMS);
    }

    public static function schoolBase(string $schoolId, string $prefix): string
    {
        return "/school-admin/{$schoolId}/{$prefix}";
    }

    /** School registration page for a fest program slug. */
    public static function schoolRegistrationUrl(string $schoolId, string $programSlug): string
    {
        return self::schoolBase($schoolId, self::prefixFromSlug($programSlug)).'/registration';
    }

    public static function sahodayaBase(string $sahodayaId, string $prefix): string
    {
        return "/sahodaya-admin/{$sahodayaId}/{$prefix}";
    }

    public static function sahodayaCatalogBase(string $sahodayaId, string $slug): string
    {
        $prefix = self::prefixFromSlug($slug);

        if ($slug === 'custom') {
            return "/sahodaya-admin/{$sahodayaId}/programs/custom/catalog";
        }

        return self::sahodayaBase($sahodayaId, $prefix).'/catalog';
    }
}
