<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Membership\SchoolMembershipGate;
use Illuminate\Support\Collection;

/**
 * Membership / roster freshness flags for Sahodaya-run exams (Talent Search or association model exams).
 */
class McqMembershipHealthService
{
    public function __construct(private SchoolMembershipGate $membership) {}

    /**
     * @return list<array{
     *   school_id: string,
     *   school_name: string,
     *   membership_status: ?string,
     *   is_active: bool,
     *   membership_ok: bool,
     *   registered: int,
     *   roster_students: int,
     *   roster_stale: bool,
     *   flags: list<string>
     * }>
     */
    public function memberSchoolFlags(McqExam $exam): array
    {
        $sahodayaId = $exam->tenant_id;
        $schools = Tenant::where('parent_id', $sahodayaId)
            ->where('type', 'school')
            ->orderBy('name')
            ->get(['id', 'name', 'membership_status', 'is_active']);

        $regCounts = McqRegistration::where('exam_id', $exam->id)
            ->active()
            ->selectRaw('school_id, COUNT(*) as c')
            ->groupBy('school_id')
            ->pluck('c', 'school_id');

        $rosterCounts = Student::query()
            ->whereIn('tenant_id', $schools->pluck('id'))
            ->active()
            ->selectRaw('tenant_id, COUNT(*) as c')
            ->groupBy('tenant_id')
            ->pluck('c', 'tenant_id');

        return $schools->map(function (Tenant $school) use ($regCounts, $rosterCounts) {
            $registered = (int) ($regCounts[$school->id] ?? 0);
            $roster = (int) ($rosterCounts[$school->id] ?? 0);
            $membershipOk = $this->membership->blockReason($school) === null;
            $flags = [];

            if (! $school->is_active) {
                $flags[] = 'inactive';
            }
            if ($school->membership_status === 'rejected') {
                $flags[] = 'rejected';
            } elseif (! $membershipOk) {
                $flags[] = 'membership_unpaid';
            }
            // Empty roster while the school is otherwise eligible is a freshness signal.
            $rosterStale = $membershipOk && $school->is_active && $roster === 0;
            if ($rosterStale) {
                $flags[] = 'empty_roster';
            }
            if ($registered > 0 && ! $membershipOk) {
                $flags[] = 'registered_while_blocked';
            }

            return [
                'school_id'          => $school->id,
                'school_name'        => $school->name,
                'membership_status'  => $school->membership_status,
                'is_active'          => (bool) $school->is_active,
                'membership_ok'      => $membershipOk,
                'registered'         => $registered,
                'roster_students'    => $roster,
                'roster_stale'       => $rosterStale,
                'flags'              => $flags,
            ];
        })->values()->all();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function problemSchools(McqExam $exam): Collection
    {
        return collect($this->memberSchoolFlags($exam))
            ->filter(fn (array $row) => $row['flags'] !== [])
            ->values();
    }
}
