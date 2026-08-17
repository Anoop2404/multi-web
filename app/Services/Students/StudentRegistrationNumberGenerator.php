<?php

namespace App\Services\Students;

use App\Models\RegNoCounter;
use App\Models\SahodayaProfile;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AcademicYear;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StudentRegistrationNumberGenerator
{
    /** Fixed prefix for every student ID across all Sahodayas. */
    public const PREFIX = 'STU';

    private const SEQUENCE_PAD = 4;

    /**
     * Student ID, e.g. STU/26/0001 — per-Sahodaya, per-academic-year sequence. Also used as portal username.
     *
     * O(1) per call as of the 2026-08-15 performance fix (see
     * docs/N1_AUDIT_SWEEP_2_2026_08_03.md, finding #1). This used to rescan every
     * reg_no already issued to the Sahodaya for the year on every single call
     * (Student::whereIn(...)->where('reg_no', 'like', ...)->pluck(...)->max()), which
     * is O(N) per call and O(N^2) for a bulk import/backfill of N students. It now
     * reads and atomically increments a `reg_no_counters` row keyed by
     * (sahodaya_id, year_suffix) instead — see App\Models\RegNoCounter and the
     * create_reg_no_counters_table migration (which also documents how a
     * Sahodaya+year's counter gets seeded from pre-existing reg_no data the first
     * time it's needed, since the counter table starts empty).
     */
    public function generate(Tenant $school, ?string $academicYear = null): string
    {
        $yearSuffix = $this->yearSuffixFor($school, $academicYear);
        $base = sprintf('%s/%s/', self::PREFIX, $yearSuffix);

        return DB::transaction(function () use ($school, $base, $yearSuffix) {
            // Serialize allocation across the whole Sahodaya (across processes/imports)
            // by locking the profile row for the duration of this transaction. This is
            // what makes the counter upsert below race-free: only one generate() call
            // for this Sahodaya can be inside this block at a time, so the "counter row
            // doesn't exist yet, create it" branch below can never be entered
            // concurrently by two callers for the same (sahodaya, year).
            SahodayaProfile::where('tenant_id', $school->parent_id)->lockForUpdate()->first();

            // reg_no_counters lives on the central connection (see RegNoCounter), which
            // is a different physical database than this transaction's connection when
            // TENANCY_DATABASE_PER_SAHODAYA is on. Wrap the counter read/create/update in
            // its own transaction on that connection so the increment is atomic even
            // though it can't share the outer transaction's connection.
            return DB::connection(config('tenancy.database.central_connection', 'central'))
                ->transaction(function () use ($school, $base, $yearSuffix) {
                    $counter = RegNoCounter::where('sahodaya_id', $school->parent_id)
                        ->where('year_suffix', $yearSuffix)
                        ->lockForUpdate()
                        ->first();

                    if (! $counter) {
                        // First-ever allocation for this (sahodaya, year) since the
                        // counter table was introduced. Seed it from whatever's already
                        // in use — computed the old O(N) way, but only once per
                        // (sahodaya, year) ever, not once per student, so this doesn't
                        // reintroduce the O(N^2) behavior this fix targets. Every call
                        // after this one for this (sahodaya, year) takes the O(1) path
                        // above instead.
                        $counter = RegNoCounter::create([
                            'sahodaya_id'    => $school->parent_id,
                            'year_suffix'    => $yearSuffix,
                            'last_sequence'  => $this->seedSequenceFromExistingStudents($school, $base),
                        ]);
                    }

                    $next = $counter->last_sequence + 1;
                    $counter->update(['last_sequence' => $next]);

                    return $base.str_pad((string) $next, self::SEQUENCE_PAD, '0', STR_PAD_LEFT);
                });
        });
    }

    /** Assign a formatted student ID when missing or in a legacy format. */
    public function assignMissing(Student $student, Tenant $school): ?string
    {
        if (filled($student->reg_no) && $this->hasFormattedRegNo($student->reg_no)) {
            return $this->persistIdentity($student, $student->reg_no);
        }

        $regNo = $this->generate($school, $this->academicYearForStudent($student, $school));

        return $this->persistIdentity($student, $regNo);
    }

    public function isMissingOrLegacy(Student $student): bool
    {
        if (blank($student->reg_no)) {
            return true;
        }

        return ! $this->hasFormattedRegNo($student->reg_no);
    }

    /** Ensure reg_no exists in the new format and portal username matches it. */
    public function syncIdentity(Student $student, Tenant $school): string
    {
        $student = $student->fresh();

        if ($this->isMissingOrLegacy($student)) {
            return $this->assignMissing($student, $school) ?? '';
        }

        return $this->persistIdentity($student, (string) $student->reg_no);
    }

    public function ensurePortalLoginId(Student $student, Tenant $school): string
    {
        return $this->syncIdentity($student, $school);
    }

    /** Force a specific reg_no onto the student (used by the bulk backfill command). */
    public function applyRegNo(Student $student, string $regNo): string
    {
        return $this->persistIdentity($student, $regNo);
    }

    public function hasFormattedRegNo(string $value): bool
    {
        return (bool) preg_match('/^STU\/\d{2}\/\d{3,}$/i', trim($value));
    }

    /** Build a reg_no string from a year suffix and sequence, e.g. STU/26/0001. */
    public function format(string $yearSuffix, int $sequence): string
    {
        return sprintf('%s/%s/%s', self::PREFIX, $yearSuffix, str_pad((string) $sequence, self::SEQUENCE_PAD, '0', STR_PAD_LEFT));
    }

    public function yearSuffixForStudent(Student $student, Tenant $school): string
    {
        return AcademicYear::yearSuffix($this->academicYearForStudent($student, $school));
    }

    private function academicYearForStudent(Student $student, Tenant $school): string
    {
        $label = $student->relationLoaded('academicYear')
            ? $student->academicYear?->label
            : $student->academicYear()->value('label');

        return $label ?: AcademicYear::forSchool($school);
    }

    private function persistIdentity(Student $student, string $regNo): string
    {
        if ($student->reg_no !== $regNo) {
            $student->forceFill(['reg_no' => $regNo])->save();
        }

        $this->syncPortalUsername($student->fresh(), $regNo);

        return $regNo;
    }

    private function syncPortalUsername(Student $student, string $username): void
    {
        if (! $student->user_id) {
            return;
        }

        User::whereKey($student->user_id)
            ->where('username', '!=', $username)
            ->update(['username' => $username]);
    }

    /** Resolve the two-digit-ish academic-year suffix (e.g. "26") used in the reg_no base. */
    private function yearSuffixFor(Tenant $school, ?string $academicYear = null): string
    {
        $sahodaya = $school->parent;
        abort_unless($sahodaya, new RuntimeException('School is not linked to a Sahodaya.'));

        return AcademicYear::yearSuffix($academicYear ?? AcademicYear::forSchool($school));
    }

    /**
     * One-time seed for a brand-new (sahodaya, year) counter row: the highest sequence
     * already in use for $base across every school in the Sahodaya, computed the same
     * way generate() used to compute it on every call before the reg_no_counters table
     * existed. Only ever runs once per (sahodaya, year) — after the counter row is
     * created, generate() never falls back to this again for that pair.
     */
    private function seedSequenceFromExistingStudents(Tenant $school, string $base): int
    {
        $schoolIds = $this->sahodayaSchoolIds($school);

        return Student::whereIn('tenant_id', $schoolIds)
            ->where('reg_no', 'like', $base.'%')
            ->pluck('reg_no')
            ->map(fn (?string $value) => $this->parseSequenceForBase($value, $base))
            ->max() ?? 0;
    }

    /** @return list<string> */
    private function sahodayaSchoolIds(Tenant $school): array
    {
        $ids = Tenant::where('parent_id', $school->parent_id)->pluck('id')->all();

        return $ids !== [] ? $ids : [$school->id];
    }

    private function parseSequenceForBase(?string $value, string $base): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $upper = strtoupper($value);
        $baseUpper = strtoupper($base);

        if (str_starts_with($upper, $baseUpper)) {
            $tail = substr($value, strlen($base));

            return ctype_digit($tail) ? (int) $tail : 0;
        }

        return 0;
    }
}
