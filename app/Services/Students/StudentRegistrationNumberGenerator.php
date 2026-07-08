<?php

namespace App\Services\Students;

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

    /** Student ID, e.g. STU/26/0001 — per-Sahodaya, per-academic-year sequence. Also used as portal username. */
    public function generate(Tenant $school, ?string $academicYear = null): string
    {
        $base = $this->numberBase($school, $academicYear);
        $schoolIds = $this->sahodayaSchoolIds($school);

        return DB::transaction(function () use ($school, $base, $schoolIds) {
            // Serialize allocation across the whole Sahodaya by locking the profile row.
            SahodayaProfile::where('tenant_id', $school->parent_id)->lockForUpdate()->first();

            $max = Student::whereIn('tenant_id', $schoolIds)
                ->whereNotNull('reg_no')
                ->pluck('reg_no')
                ->map(fn (?string $value) => $this->parseSequenceForBase($value, $base))
                ->max() ?? 0;

            return $base.str_pad((string) ($max + 1), self::SEQUENCE_PAD, '0', STR_PAD_LEFT);
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

    private function numberBase(Tenant $school, ?string $academicYear = null): string
    {
        $sahodaya = $school->parent;
        abort_unless($sahodaya, new RuntimeException('School is not linked to a Sahodaya.'));

        $yearSuffix = AcademicYear::yearSuffix($academicYear ?? AcademicYear::forSchool($school));

        return sprintf('%s/%s/', self::PREFIX, $yearSuffix);
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
