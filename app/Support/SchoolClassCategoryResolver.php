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
    public static function feeTierFor(Tenant $school, array $schedule = []): string
    {
        $classTier = self::feeTierLabel(self::highestClassGroupKeyFor($school));

        $secMinStudents = isset($schedule['secondary_min_students']) && $schedule['secondary_min_students'] !== '' && $schedule['secondary_min_students'] !== null
            ? (int) $schedule['secondary_min_students']
            : null;

        if ($classTier === 'secondary' && $secMinStudents !== null) {
            $studentCount = \App\Models\Student::where('tenant_id', $school->id)
                ->where('status', 'active')
                ->count();

            if ($studentCount <= $secMinStudents) {
                return 'other';
            }
        }

        return $classTier;
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

        try {
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
        } catch (\Throwable) {
            // school_classes table is tenant-scoped and may not exist on central DB connection
        }

        $payload = $school->application_payload ?? [];
        $rawHighest = $payload['highest_class'] ?? $payload['highest_class_offered'] ?? $school->school_category ?? null;

        if ($rawHighest) {
            $str = strtolower((string) $rawHighest);
            $profileKey = match (true) {
                str_contains($str, '12') || str_contains($str, '11') || str_contains($str, 'senior') || str_contains($str, 'hss') => 'hss',
                str_contains($str, '10') || str_contains($str, '9') || str_contains($str, 'high') || str_contains($str, 'secondary') || str_contains($str, 'hs') => 'hs',
                str_contains($str, '8') || str_contains($str, '7') || str_contains($str, '6') || str_contains($str, 'upper') || str_contains($str, 'up') => 'up',
                str_contains($str, '5') || str_contains($str, '4') || str_contains($str, '3') || str_contains($str, '2') || str_contains($str, '1') || str_contains($str, 'primary') || str_contains($str, 'lp') => 'lp',
                default => null,
            };

            if ($profileKey !== null) {
                $profileRank = array_search($profileKey, FestClassGroupScheme::KEYS, true);
                if ($profileRank !== false && $profileRank > $highestRank) {
                    $highestRank = $profileRank;
                    $highestKey = $profileKey;
                }
            }
        }

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
