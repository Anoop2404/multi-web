<?php

namespace App\Console\Commands;

use App\Models\Registration;
use App\Models\Tenant;
use App\Support\AcademicYear;
use Illuminate\Console\Command;

class UpdateMembershipStatusCommand extends Command
{
    protected $signature = 'membership:update-renewal-status';

    protected $description = 'Update school renewal_status based on annual registration completion';

    public function handle(): int
    {
        $year = AcademicYear::current();
        $years = AcademicYear::options();
        $currentIndex = array_search($year, $years, true);
        $priorYear = ($currentIndex !== false && isset($years[$currentIndex + 1])) ? $years[$currentIndex + 1] : null;

        Tenant::query()
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->chunkById(100, function ($schools) use ($year, $priorYear) {
                foreach ($schools as $school) {
                    $current = Registration::where('school_id', $school->id)
                        ->where('academic_year', $year)
                        ->first();

                    if ($current && in_array($current->registration_status, ['completed', 'approved'], true)) {
                        $school->update(['renewal_status' => 'active']);

                        continue;
                    }

                    if ($priorYear) {
                        $prior = Registration::where('school_id', $school->id)
                            ->where('academic_year', $priorYear)
                            ->first();

                        if ($prior && in_array($prior->registration_status, ['completed', 'approved'], true)) {
                            $school->update(['renewal_status' => 'renewal_due']);

                            continue;
                        }
                    }

                    $lastCompleted = Registration::where('school_id', $school->id)
                        ->whereIn('registration_status', ['completed', 'approved'])
                        ->orderByDesc('academic_year')
                        ->first();

                    $school->update([
                        'renewal_status' => $lastCompleted ? 'lapsed' : null,
                    ]);
                }
            });

        $this->info('Renewal status updated.');

        return self::SUCCESS;
    }
}
