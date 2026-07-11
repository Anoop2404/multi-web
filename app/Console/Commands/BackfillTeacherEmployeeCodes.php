<?php

namespace App\Console\Commands;

use App\Models\Teacher;
use App\Models\Tenant;
use App\Services\Auth\EmployeeCodeGenerator;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;

class BackfillTeacherEmployeeCodes extends Command
{
    protected $signature = 'erp:backfill-teacher-employee-codes
                            {--sahodaya= : Sahodaya tenant id (all schools under it)}
                            {--school= : Single school tenant id}';

    protected $description = 'Assign EMP/{school_prefix}/00001 employee codes to teachers missing one';

    public function handle(EmployeeCodeGenerator $generator): int
    {
        $schoolId = $this->option('school');
        $sahodayaId = $this->option('sahodaya');

        if ($schoolId) {
            $school = Tenant::query()->where('type', 'school')->findOrFail($schoolId);
            $sahodaya = Tenant::query()->findOrFail($school->parent_id);
            TenancyDatabase::withTenantDatabase($sahodaya, fn () => $this->backfillSchool($school, $generator));

            return self::SUCCESS;
        }

        $sahodayas = $sahodayaId
            ? Tenant::query()->where('type', 'sahodaya')->whereKey($sahodayaId)->get()
            : Tenant::query()->where('type', 'sahodaya')->get();

        foreach ($sahodayas as $sahodaya) {
            $this->info("Sahodaya: {$sahodaya->name} ({$sahodaya->id})");
            TenancyDatabase::withTenantDatabase($sahodaya, function () use ($sahodaya, $generator) {
                Tenant::query()
                    ->where('type', 'school')
                    ->where('parent_id', $sahodaya->id)
                    ->orderBy('name')
                    ->each(fn (Tenant $school) => $this->backfillSchool($school, $generator));
            });
        }

        return self::SUCCESS;
    }

    private function backfillSchool(Tenant $school, EmployeeCodeGenerator $generator): void
    {
        $assigned = 0;

        Teacher::query()
            ->where('tenant_id', $school->id)
            ->whereNull('employee_code')
            ->orderBy('id')
            ->each(function (Teacher $teacher) use ($generator, &$assigned) {
                try {
                    $code = $generator->assign($teacher->fresh());
                    $assigned++;
                    $this->line("  {$teacher->name} ({$teacher->id}) → {$code}");
                } catch (\Throwable $e) {
                    $this->error("  {$teacher->name} ({$teacher->id}): {$e->getMessage()}");
                }
            });

        $this->info("  {$school->name}: assigned {$assigned} employee code(s).");
    }
}
