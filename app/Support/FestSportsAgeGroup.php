<?php

namespace App\Support;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\SahodayaProfile;
use App\Models\Student;
use App\Services\Events\FestSportsAgeGroupRegistry;
use Carbon\Carbon;

class FestSportsAgeGroup
{
    /** @var list<string> */
    public const KEYS = ['u8', 'u10', 'u11', 'u12', 'u14', 'u17', 'u19', 'open'];

    /**
     * Cached per tenant per request. FestSportsAgeGroupRegistry::forTenant() returns
     * a fresh clone with its internal row cache reset, and this method used to be
     * called ~20-30 times per student during eligibility annotation — for a school
     * with 2000 students that meant 100,000+ redundant DB queries per page load
     * (each clone re-runs an exists() check plus a fresh get() query). Caching the
     * per-tenant registry instance here means the clone — and its row cache — is
     * built once per tenant per request.
     *
     * @var array<string, FestSportsAgeGroupRegistry>
     */
    private static array $registryCache = [];

    private static function registry(?string $tenantId = null): FestSportsAgeGroupRegistry
    {
        if (! $tenantId) {
            return app(FestSportsAgeGroupRegistry::class);
        }

        return self::$registryCache[$tenantId] ??= app(FestSportsAgeGroupRegistry::class)->forTenant($tenantId);
    }

    /** @return array<string, string> */
    public static function labels(?string $tenantId = null): array
    {
        if ($tenantId) {
            return self::registry($tenantId)->labels();
        }

        $groups = config('fest_co_curricular.sports.age_groups', []);

        return collect($groups)->mapWithKeys(fn ($row, $key) => [
            $key => is_array($row) ? ($row['label'] ?? strtoupper($key)) : (string) $row,
        ])->all();
    }

    /** @return array<string, float> */
    public static function defaultFees(?string $tenantId = null): array
    {
        if ($tenantId) {
            return self::registry($tenantId)->defaultFees();
        }

        return config('fest_sports_age_groups.default_fees', []);
    }

    public static function isValid(?string $ageGroup, ?string $tenantId = null): bool
    {
        if (! filled($ageGroup)) {
            return false;
        }

        if ($tenantId) {
            return self::registry($tenantId)->isValid($ageGroup);
        }

        return in_array($ageGroup, self::KEYS, true);
    }

    public static function fromClassGroup(?string $classGroup): ?string
    {
        if (! filled($classGroup)) {
            return null;
        }

        $mapped = config("fest_sports_age_groups.class_group_map.{$classGroup}");

        return self::isValid($mapped) ? $mapped : null;
    }

    public static function resolveForItem(?string $ageGroup, ?string $classGroup, string $eventType): ?string
    {
        if ($eventType !== 'sports') {
            return null;
        }

        if (self::isValid($ageGroup)) {
            return $ageGroup;
        }

        return self::fromClassGroup($classGroup);
    }

    public static function genderLabel(?string $gender): ?string
    {
        return match (strtolower((string) ($gender ?? ''))) {
            'male'   => 'Boys',
            'female' => 'Girls',
            'mixed'  => 'Mixed',
            default  => null,
        };
    }

    /**
     * Sahodaya-wide default age reference date (Configuration → Age groups),
     * used whenever an event doesn't set its own override. Cached per-request
     * per tenant to avoid repeat queries when scoring many students/items.
     *
     * @var array<string, ?Carbon>
     */
    private static array $globalCutoffCache = [];

    public static function globalCutoffDate(?string $tenantId): ?Carbon
    {
        if (! $tenantId) {
            return null;
        }

        if (array_key_exists($tenantId, self::$globalCutoffCache)) {
            return self::$globalCutoffCache[$tenantId];
        }

        $raw = SahodayaProfile::where('tenant_id', $tenantId)->value('sports_age_cutoff_date');

        return self::$globalCutoffCache[$tenantId] = $raw ? Carbon::parse($raw)->startOfDay() : null;
    }

    public static function competitionYear(FestEvent $event): int
    {
        if ($event->sports_age_cutoff_date) {
            return (int) $event->sports_age_cutoff_date->format('Y');
        }

        if ($global = self::globalCutoffDate($event->tenant_id)) {
            return (int) $global->format('Y');
        }

        $date = $event->event_end ?? $event->event_start ?? $event->registration_close ?? now();

        return (int) Carbon::parse($date)->format('Y');
    }

    /**
     * Date on which student age is counted for Under-N eligibility.
     * Priority: this event's own override → Sahodaya-wide default (Age groups
     * config) → computed fallback (competition year + configured month/day).
     */
    public static function cutoffDate(FestEvent $event): Carbon
    {
        if ($event->sports_age_cutoff_date) {
            return Carbon::parse($event->sports_age_cutoff_date)->startOfDay();
        }

        if ($global = self::globalCutoffDate($event->tenant_id)) {
            return $global;
        }

        $year = self::competitionYear($event);
        [$month, $day] = explode('-', config('fest_co_curricular.sports.cutoff_month_day', '12-31'));

        return Carbon::create($year, (int) $month, (int) $day)->startOfDay();
    }

    public static function ageRuleSummary(FestEvent $event): string
    {
        $cutoff = self::cutoffDate($event);

        return 'Age counted as of '.$cutoff->format('j M Y').'. Under-N items accept any student whose age on that date is less than N (e.g. U14 = under 14 years).';
    }

    /** Student age in whole years on a given reference date. */
    public static function ageOnDate(Student $student, Carbon $cutoff): ?int
    {
        if (! $student->dob) {
            return null;
        }

        $dob = Carbon::parse($student->dob)->startOfDay();
        $age = $cutoff->year - $dob->year;

        if ($cutoff->format('md') < $dob->format('md')) {
            $age--;
        }

        return $age;
    }

    /** Student age in whole years on the event age cutoff date. */
    public static function ageOnCutoff(Student $student, FestEvent $event): ?int
    {
        if (! $student->dob) {
            return null;
        }

        return self::ageOnDate($student, self::cutoffDate($event));
    }

    /**
     * The Sahodaya-wide default reference date for a tenant, with no event in
     * play (e.g. the general student roster): the global config if set,
     * otherwise 31 Dec of the current year.
     */
    public static function defaultCutoffDateForTenant(string $tenantId): Carbon
    {
        if ($global = self::globalCutoffDate($tenantId)) {
            return $global;
        }

        [$month, $day] = explode('-', config('fest_co_curricular.sports.cutoff_month_day', '12-31'));

        return Carbon::create((int) now()->format('Y'), (int) $month, (int) $day)->startOfDay();
    }

    /**
     * The single age category a student falls into using the Sahodaya-wide
     * default reference date — for contexts with no specific FestEvent (e.g.
     * the general student roster). Mirrors assignedAgeGroupForStudent() but
     * without requiring an event.
     */
    public static function assignedAgeGroupForStudentByTenant(Student $student, string $tenantId): ?string
    {
        if (! $student->dob) {
            return null;
        }

        $cutoff = self::defaultCutoffDateForTenant($tenantId);

        foreach (self::orderedAgeGroups($tenantId) as $group) {
            $underAge = self::underAge($group, $tenantId);
            if (! $underAge) {
                continue;
            }

            $age = self::ageOnDate($student, $cutoff);
            if ($age !== null && $age < $underAge) {
                return $group;
            }
        }

        return null;
    }

    /** Minimum birth date (inclusive) to qualify for Under-N on the event age reference date. */
    public static function birthDateOnOrAfter(string $ageGroup, FestEvent $event): ?Carbon
    {
        if ($ageGroup === 'open') {
            return null;
        }

        $underAge = self::underAge($ageGroup, $event->tenant_id);
        if (! $underAge) {
            return null;
        }

        return self::cutoffDate($event)->copy()->subYears($underAge)->addDay()->startOfDay();
    }

    /** @return list<string> */
    public static function orderedAgeGroups(?string $tenantId = null): array
    {
        if ($tenantId) {
            return self::registry($tenantId)->orderedAgeGroups();
        }

        return ['u8', 'u10', 'u11', 'u12', 'u14', 'u17', 'u19'];
    }

    public static function underAge(string $ageGroup, ?string $tenantId = null): ?int
    {
        if ($tenantId) {
            return self::registry($tenantId)->underAge($ageGroup);
        }

        return config("fest_co_curricular.sports.age_groups.{$ageGroup}.under_age");
    }

    /**
     * The single age category a student competes in (tightest Under-N band they qualify for).
     */
    public static function assignedAgeGroupForStudent(Student $student, FestEvent $event): ?string
    {
        if (! $student->dob) {
            return null;
        }

        foreach (self::orderedAgeGroups($event->tenant_id) as $group) {
            if (self::qualifiesForAgeGroup($student, $group, $event)) {
                return $group;
            }
        }

        return null;
    }

    public static function qualifiesForAgeGroup(Student $student, string $ageGroup, FestEvent $event): bool
    {
        if ($ageGroup === 'open') {
            return true;
        }

        if (! $student->dob) {
            return false;
        }

        if (! self::isValid($ageGroup, $event->tenant_id)) {
            return false;
        }

        $underAge = self::underAge($ageGroup, $event->tenant_id);
        if (! $underAge) {
            return false;
        }

        $age = self::ageOnCutoff($student, $event);

        return $age !== null && $age < $underAge;
    }

    /** Highest matching standard cluster age group for a student (excludes open). */
    public static function primaryAgeGroupForStudent(Student $student, FestEvent $event): ?string
    {
        return self::assignedAgeGroupForStudent($student, $event);
    }

    /** @return list<string> All Under-N bands this student qualifies for on the cutoff date (for item registration). */
    public static function eligibleAgeGroupsForStudent(Student $student, FestEvent $event): array
    {
        if (! $student->dob) {
            return [];
        }

        $groups = [];
        foreach (self::orderedAgeGroups($event->tenant_id) as $group) {
            if (self::qualifiesForAgeGroup($student, $group, $event)) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    public static function qualifiesForItem(Student $student, FestEventItem $item, FestEvent $event): bool
    {
        $itemAge = self::resolveForItem($item->age_group, $item->class_group, 'sports');
        if ($itemAge === null || $itemAge === 'open') {
            return (bool) $student->dob;
        }

        return self::qualifiesForAgeGroup($student, $itemAge, $event);
    }

    public static function itemEligibilityLabel(FestEventItem $item, FestEvent $event): string
    {
        $parts = [];

        if ($event->event_type === 'sports') {
            $age = self::resolveForItem($item->age_group, $item->class_group, 'sports');
            if ($age && $age !== 'open') {
                $parts[] = self::labels($event->tenant_id)[$age] ?? strtoupper($age);
            }
        } elseif (in_array($event->event_type, ['kalolsavam', 'custom'], true)
            && filled($item->class_group)
            && $item->class_group !== 'open') {
            $labels = FestClassGroupScheme::labels(null, $event);
            $parts[] = $labels[$item->class_group] ?? strtoupper($item->class_group);
        } elseif ($event->event_type === 'kids_fest' && filled($item->kids_band) && $item->kids_band !== 'open') {
            $parts[] = FestKidsFestBand::labels()[$item->kids_band] ?? $item->kids_band;
        }

        if ($gender = self::genderLabel($item->gender)) {
            $parts[] = $gender;
        }

        return $parts !== [] ? implode(' · ', $parts) : 'Open';
    }
}
