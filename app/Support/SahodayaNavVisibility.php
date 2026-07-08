<?php

namespace App\Support;

use App\Models\SahodayaProfile;

class SahodayaNavVisibility
{
    /** @return list<string> */
    public static function programSlugs(): array
    {
        return [
            'kalotsav',
            'sports-meet',
            'kids-fest',
            'teacher-fest',
            'english-fest',
            'science-fest',
            'custom',
        ];
    }

    /** @return array<string, string> */
    public static function programLabels(): array
    {
        return [
            'kalotsav'      => 'Kalotsav',
            'sports-meet'   => 'Sports Meet',
            'kids-fest'     => 'Kids Fest',
            'teacher-fest'  => 'Teacher Fest',
            'english-fest'  => 'English Fest',
            'science-fest'  => 'Science Fest',
            'custom'        => 'Custom events',
        ];
    }

    /** @return array<string, string> */
    public static function menuLabels(): array
    {
        return [
            'website'               => 'Website section',
            'membership'            => 'Membership section',
            'mcq'                   => 'Talent Search exams',
            'training'              => 'Training programs',
            'finance'               => 'Finance hub & ledger',
            'fest_payments'         => 'Fest payments',
            'fest_appeals'          => 'Appeals queue',
            'display_screens'       => 'Display screens',
            'certificate_templates' => 'Certificate templates',
        ];
    }

    /** @return array{programs: array<string, bool>, menus: array<string, bool>} */
    public static function defaults(): array
    {
        return [
            'programs' => array_fill_keys(self::programSlugs(), true),
            'menus'    => array_fill_keys(array_keys(self::menuLabels()), true),
        ];
    }

    /**
     * Effective sidebar visibility for a Sahodaya.
     *
     * @param  array<string, mixed>|null  $platformOverrides  Super-admin hard cap: any key set
     *         to false is force-hidden regardless of the Sahodaya's own preference.
     * @return array{programs: array<string, bool>, menus: array<string, bool>}
     */
    public static function forProfile(?SahodayaProfile $profile, ?array $platformOverrides = null): array
    {
        $stored = is_array($profile?->nav_visibility) ? $profile->nav_visibility : [];
        $defaults = self::defaults();

        $visibility = [
            'programs' => array_merge($defaults['programs'], $stored['programs'] ?? []),
            'menus'    => array_merge($defaults['menus'], $stored['menus'] ?? []),
        ];

        return self::applyOverride($visibility, $platformOverrides);
    }

    /**
     * Force-disable any menu/program the platform has switched off. A `false` in the
     * override wins; anything else leaves the Sahodaya's own choice intact.
     *
     * @param  array{programs: array<string, bool>, menus: array<string, bool>}  $visibility
     * @param  array<string, mixed>|null  $overrides
     * @return array{programs: array<string, bool>, menus: array<string, bool>}
     */
    public static function applyOverride(array $visibility, ?array $overrides): array
    {
        if (! is_array($overrides)) {
            return $visibility;
        }

        foreach (['programs', 'menus'] as $group) {
            foreach (($overrides[$group] ?? []) as $key => $allowed) {
                if (filter_var($allowed, FILTER_VALIDATE_BOOLEAN) === false) {
                    $visibility[$group][$key] = false;
                }
            }
        }

        return $visibility;
    }

    public static function isProgramVisible(?SahodayaProfile $profile, string $slug): bool
    {
        $visibility = self::forProfile($profile);

        return (bool) ($visibility['programs'][$slug] ?? true);
    }

    public static function isMenuVisible(?SahodayaProfile $profile, string $key): bool
    {
        $visibility = self::forProfile($profile);

        return (bool) ($visibility['menus'][$key] ?? true);
    }

    /** @return list<string> */
    public static function visibleProgramSlugs(?SahodayaProfile $profile): array
    {
        $visibility = self::forProfile($profile);

        return array_values(array_filter(
            self::programSlugs(),
            fn (string $slug) => (bool) ($visibility['programs'][$slug] ?? true),
        ));
    }

    /** @param  array<string, mixed>  $input */
    public static function normalizeInput(array $input): array
    {
        $defaults = self::defaults();
        $programs = [];
        $menus = [];

        foreach ($defaults['programs'] as $slug => $default) {
            $programs[$slug] = filter_var($input['programs'][$slug] ?? $default, FILTER_VALIDATE_BOOLEAN);
        }

        foreach ($defaults['menus'] as $key => $default) {
            $menus[$key] = filter_var($input['menus'][$key] ?? $default, FILTER_VALIDATE_BOOLEAN);
        }

        return compact('programs', 'menus');
    }
}
