<?php

namespace App\Services\Membership;

use App\Models\Registration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class SchoolMembershipNumberGenerator
{
    /** Annual school membership number, e.g. MLCS/AMU/0001 */
    public function generate(Tenant $school): string
    {
        $sahodaya = $school->parent;
        $profile = SahodayaProfile::where('tenant_id', $sahodaya->id)->firstOrFail();

        if (! $profile->prefix) {
            throw new \RuntimeException('Sahodaya prefix is not set.');
        }

        if (! $school->school_prefix) {
            throw new \RuntimeException('School code is not set.');
        }

        return DB::transaction(function () use ($school, $profile) {
            SahodayaProfile::where('tenant_id', $school->parent_id)->lockForUpdate()->firstOrFail();

            $sequence = Registration::where('school_id', $school->id)
                ->whereNotNull('reg_no')
                ->count() + 1;

            $membershipNo = sprintf(
                '%s/%s/%04d',
                strtoupper($profile->prefix),
                strtoupper($school->school_prefix),
                $sequence,
            );

            if (! $profile->prefixes_locked) {
                $profile->update(['prefixes_locked' => true]);
            }
            if (! $school->prefixes_locked) {
                $school->update(['prefixes_locked' => true]);
            }

            return $membershipNo;
        });
    }
}
