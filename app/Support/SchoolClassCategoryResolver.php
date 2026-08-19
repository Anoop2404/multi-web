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
     * school's active SchoolClass rows, SchoolYearStudentCount rows, or application payload attributes.
     */
    public static function highestClassGroupKeyFor(Tenant $school): ?string
    {
        $highestKey = null;
        $highestRank = -1;

        $updateHighest = function (?string $key) use (&$highestKey, &$highestRank) {
            if (! $key) {
                return;
            }
            $rank = array_search($key, FestClassGroupScheme::KEYS, true);
            if ($rank !== false && $rank > $highestRank) {
                $highestRank = $rank;
                $highestKey = $key;
            }
        };

        // 1. Inspect SchoolClass rows for this school
        try {
            SchoolClass::query()
                ->where('tenant_id', $school->id)
                ->active()
                ->with('classCategory')
                ->get()
                ->each(function (SchoolClass $schoolClass) use ($updateHighest) {
                    if ($schoolClass->classCategory) {
                        $updateHighest(self::classGroupKeyForCategory($schoolClass->classCategory));
                        $updateHighest(self::classGroupKeyFromText($schoolClass->classCategory->name ?? $schoolClass->classCategory->label ?? null));
                    }
                    $updateHighest(self::classGroupKeyFromText($schoolClass->name));
                });
        } catch (\Throwable) {
            // school_classes table is tenant-scoped and may not exist on central DB connection
        }

        // 2. Inspect SchoolYearStudentCount rows submitted for this school where total_count > 0
        try {
            \App\Models\SchoolYearStudentCount::query()
                ->whereHas('submission', fn ($q) => $q->where('school_id', $school->id))
                ->where('total_count', '>', 0)
                ->with(['schoolClass.classCategory', 'classCategory'])
                ->get()
                ->each(function (\App\Models\SchoolYearStudentCount $count) use ($updateHighest) {
                    if ($count->classCategory) {
                        $updateHighest(self::classGroupKeyForCategory($count->classCategory));
                        $updateHighest(self::classGroupKeyFromText($count->classCategory->name ?? $count->classCategory->label ?? null));
                    }
                    if ($count->schoolClass) {
                        if ($count->schoolClass->classCategory) {
                            $updateHighest(self::classGroupKeyForCategory($count->schoolClass->classCategory));
                            $updateHighest(self::classGroupKeyFromText($count->schoolClass->classCategory->name ?? $count->schoolClass->classCategory->label ?? null));
                        }
                        $updateHighest(self::classGroupKeyFromText($count->schoolClass->name));
                    }
                });
        } catch (\Throwable) {
        }

        // 3. Inspect application_payload and school attributes
        $payload = $school->application_payload ?? [];
        $candidates = [
            $payload['highest_class'] ?? null,
            $payload['highest_class_offered'] ?? null,
            $payload['school_category'] ?? null,
            $payload['institution_type'] ?? null,
            $payload['affiliation_category'] ?? null,
            $school->school_category ?? null,
        ];

        foreach ($candidates as $raw) {
            if ($raw) {
                $updateHighest(self::classGroupKeyFromText((string) $raw));
            }
        }

        return $highestKey;
    }

    /**
     * Infer class group key (lp/up/hs/hss) directly from text representation of a class or category name.
     */
    public static function classGroupKeyFromText(?string $text): ?string
    {
        if (blank($text)) {
            return null;
        }

        $str = mb_strtolower(trim((string) $text));

        if (str_contains($str, '12')
            || str_contains($str, '11')
            || str_contains($str, 'xii')
            || str_contains($str, 'xi')
            || str_contains($str, 'senior')
            || str_contains($str, 'higher')
            || str_contains($str, 'hss')
            || str_contains($str, '+2')
            || str_contains($str, 'plus two')
            || str_contains($str, 'plus 2')) {
            return 'hss';
        }

        if (str_contains($str, '10')
            || str_contains($str, '9')
            || str_contains($str, 'high school')
            || str_contains($str, 'secondary')
            || str_contains($str, 'hs')) {
            return 'hs';
        }

        if (str_contains($str, '8')
            || str_contains($str, '7')
            || str_contains($str, '6')
            || str_contains($str, 'upper primary')
            || str_contains($str, 'up')) {
            return 'up';
        }

        if (str_contains($str, '5')
            || str_contains($str, '4')
            || str_contains($str, '3')
            || str_contains($str, '2')
            || str_contains($str, '1')
            || str_contains($str, 'primary')
            || str_contains($str, 'lp')) {
            return 'lp';
        }

        return null;
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
