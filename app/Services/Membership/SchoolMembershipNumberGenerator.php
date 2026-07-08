<?php

namespace App\Services\Membership;

use App\Models\Registration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Support\AcademicYear;
use Illuminate\Support\Facades\DB;

class SchoolMembershipNumberGenerator
{
    /**
     * School membership number, e.g. KNR/26/1 — per-Sahodaya sequence that
     * restarts from 1 each academic year. Stable per school within a year.
     */
    public function generate(Tenant $school, ?string $academicYear = null): string
    {
        $sahodaya = $school->parent;
        abort_unless($sahodaya, new \RuntimeException('School is not linked to a Sahodaya.'));

        $profile = SahodayaProfile::where('tenant_id', $sahodaya->id)->firstOrFail();

        if (! $profile->prefix) {
            throw new \RuntimeException('Sahodaya prefix is not set.');
        }

        $academicYear ??= AcademicYear::forSchool($school);
        $prefix = strtoupper($profile->prefix);
        $base = $prefix.'/'.AcademicYear::yearSuffix($academicYear).'/';

        return DB::transaction(function () use ($school, $profile, $base, $academicYear) {
            SahodayaProfile::where('tenant_id', $school->parent_id)->lockForUpdate()->firstOrFail();

            $existing = $this->existingForSchool($school, $academicYear, $base);
            if ($existing !== null) {
                return $existing;
            }

            $sequence = $this->nextSequenceForSahodaya($school->parent_id, $academicYear, $base);

            if (! $profile->prefixes_locked) {
                $profile->update(['prefixes_locked' => true]);
            }

            return $base.$sequence;
        });
    }

    public function hasFormattedMembershipNo(string $value, ?string $base = null): bool
    {
        $value = trim($value);
        if ($base !== null) {
            return (bool) preg_match('/^'.preg_quote(strtoupper($base), '/').'\d+$/i', strtoupper($value));
        }

        return (bool) preg_match('/^[A-Z0-9]+\/\d{2}\/\d+$/i', $value);
    }

    private function existingForSchool(Tenant $school, string $academicYear, string $base): ?string
    {
        return Registration::where('school_id', $school->id)
            ->where('academic_year', $academicYear)
            ->whereNotNull('reg_no')
            ->orderBy('id')
            ->pluck('reg_no')
            ->first(fn (string $regNo) => $this->hasFormattedMembershipNo($regNo, $base));
    }

    private function nextSequenceForSahodaya(string $sahodayaId, string $academicYear, string $base): int
    {
        $schoolIds = Tenant::where('parent_id', $sahodayaId)->pluck('id');

        $max = Registration::whereIn('school_id', $schoolIds)
            ->where('academic_year', $academicYear)
            ->whereNotNull('reg_no')
            ->pluck('reg_no')
            ->map(fn (string $regNo) => $this->parseSequence($regNo, $base))
            ->max() ?? 0;

        return max(1, $max + 1);
    }

    private function parseSequence(string $regNo, string $base): int
    {
        $pattern = '/^'.preg_quote(strtoupper($base), '/').'(\d+)$/i';
        if (preg_match($pattern, strtoupper(trim($regNo)), $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
}
