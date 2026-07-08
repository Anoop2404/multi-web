<?php

namespace App\Jobs;

use App\Models\Student;
use App\Models\Tenant;
use App\Services\Portal\StudentPortalProvisioner;
use App\Support\TenancyDatabase;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProvisionPortalUsersJob implements ShouldQueue
{
    use Queueable;

    /** @param  list<int>  $studentIds */
    public function __construct(
        public string $schoolId,
        public array $studentIds,
        public int $requestedByUserId,
    ) {}

    public function handle(StudentPortalProvisioner $provisioner): void
    {
        $school = Tenant::query()->find($this->schoolId);
        if (! $school || $school->type !== 'school') {
            return;
        }

        TenancyDatabase::withTenantDatabase($school, function () use ($school, $provisioner) {
            $students = Student::query()
                ->where('tenant_id', $school->id)
                ->whereIn('id', $this->studentIds)
                ->whereNull('user_id')
                ->whereNotNull('reg_no')
                ->get();

            foreach ($students as $student) {
                try {
                    $provisioner->ensureRegNoLogin($student);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        });
    }
}
