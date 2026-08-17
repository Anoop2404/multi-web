<?php

namespace App\Support;

use App\Models\ClassCategory;
use App\Models\SchoolClass;
use App\Models\Tenant;

/**
 * Derives a school's Kalotsavam fee tier from the classes it has actually opted to run,
 * rather than a manually-set `institution_level` tag. See
 * docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md §7.4 — "3+ tier school-type registration fee,
 * tier derived from classes actually opted".
 *
 * A school's tier is the *highest* class category among its own active SchoolClass rows,
 * using FestClassGroupScheme::KEYS's built-in ordered hierarchy (lp < up < hs < hss <
 * open). The resulting tier label intentionally reuses the exact 'secondary'/
 * 'senior_secondary' keys FestSchoolEventFeeService::schoolRegistrationAmount() has
 * always looked up (plus a new 'other' bucket for schools with nothing above upper
 * primary) — an event whose fee_settings.school_registration only configures those two
 * keys therefore keeps behaving exactly as it does today; a school landing on 'other'
 * simply falls through to that lookup's existing 'secondary' fallback, same as a school
 * with no institution_level ever did.
 */
class SchoolClassCategoryResolver
{
    /** Highest active-class fee tier for this school, e.g. 'secondary', 'senior_secondary', 'other'. */
    public static function feeTierFor(Tenant $school): string
    {
        return self::feeTierLabel(self::highestClassGroupKeyFor($school));
    }

    /**
     * The highest FestClassGroupScheme key (lp/up/hs/hss/open) reached by any of this
     * school's active SchoolClass rows, or null if it has none classifiable (no active
     * classes, or only categories with no class range set, e.g. Pre-Primary).
     */
    public static function highestClassGroupKeyFor(Tenant $school): ?string
    {
        $highestKey = null;
        $highestRank = -1;

        SchoolClass::query()
            ->where('tenant_id', $school->id)
            ->active()
            ->with('classCategory')
            ->get()
            ->each(function (SchoolClass $schoolClass) use (&$highestKey, &$highestRank) {
                $category = $schoolClass->classCategory;
                if (! $category) {
                    return;
                }

                $key = self::classGroupKeyForCategory($category);
                if ($key === null) {
                    return;
                }

                $rank = array_search($key, FestClassGroupScheme::KEYS, true);
                if ($rank !== false && $rank > $highestRank) {
                    $highestRank = $rank;
                    $highestKey = $key;
                }
            });

        return $highestKey;
    }

    /**
     * Bucket a ClassCategory (Pre-Primary/Primary/Upper Primary/High School/Higher
     * Secondary, or a Sahodaya's own custom equivalents) into the built-in lp/up/hs/hss
     * hierarchy using its class range. Mirrors the boundaries the default global
     * categories already ship with (SahodayaMasterDataSeeder: Primary 1-5, Upper Primary
     * 6-8, High School 9-10, Higher Secondary 11-12). A category with no class range at
     * all (e.g. Pre-Primary) returns null — it doesn't raise a school's tier.
     */
    private static function classGroupKeyForCategory(ClassCategory $category): ?string
    {
        $max = $category->max_class ?? $category->min_class;
        if ($max === null) {
            return null;
        }

        return match (true) {
            $max >= 11 => 'hss',
            $max >= 9 => 'hs',
            $max >= 6 => 'up',
            default => 'lp',
        };
    }

    /** @see class-level doc comment for why these three labels specifically. */
    private static function feeTierLabel(?string $classGroupKey): string
    {
        return match ($classGroupKey) {
            'hss', 'open' => 'senior_secondary',
            'hs' => 'secondary',
            default => 'other', // lp, up, or no classifiable active classes at all
        };
    }
}
