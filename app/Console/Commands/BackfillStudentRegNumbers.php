<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\Tenant;
use App\Services\Students\StudentRegistrationNumberGenerator;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;

class BackfillStudentRegNumbers extends Command
{
    protected $signature = 'erp:backfill-student-reg-numbers
                            {--sahodaya= : Sahodaya tenant id (all schools under it)}
                            {--school= : Single school tenant id}
                            {--reformat : Renumber every student, even those already in the STU format}';

    protected $description = 'Assign STU/YY/0001 student IDs (per-Sahodaya, per-year) to records missing one';

    public function handle(StudentRegistrationNumberGenerator $generator): int
    {
        $schoolId = $this->option('school');
        $sahodayaId = $this->option('sahodaya');
        $reformat = (bool) $this->option('reformat');

        if ($schoolId) {
            $school = Tenant::query()->where('type', 'school')->findOrFail($schoolId);
            $sahodaya = Tenant::query()->findOrFail($school->parent_id);
            TenancyDatabase::withTenantDatabase($sahodaya, fn () => $this->backfillSchool($school, $generator, $reformat));

            return self::SUCCESS;
        }

        $sahodayas = $sahodayaId
            ? Tenant::query()->where('type', 'sahodaya')->whereKey($sahodayaId)->get()
            : Tenant::query()->where('type', 'sahodaya')->get();

        foreach ($sahodayas as $sahodaya) {
            $this->info("Sahodaya: {$sahodaya->name} ({$sahodaya->id})");
            TenancyDatabase::withTenantDatabase($sahodaya, function () use ($sahodaya, $generator, $reformat) {
                Tenant::query()
                    ->where('type', 'school')
                    ->where('parent_id', $sahodaya->id)
                    ->orderBy('name')
                    ->each(fn (Tenant $school) => $this->backfillSchool($school, $generator, $reformat));
            });
        }

        return self::SUCCESS;
    }

    private function backfillSchool(Tenant $school, StudentRegistrationNumberGenerator $generator, bool $reformat): void
    {
        $assigned = 0;

        Student::query()
            ->where('tenant_id', $school->id)
            ->with('academicYear')
            ->orderBy('id')
            ->each(function (Student $student) use ($school, $generator, $reformat, &$assigned) {
                if (! $reformat && ! $generator->isMissingOrLegacy($student)) {
                    return;
                }

                try {
                    // Use the student's own academic year for the year segment, and take
                    // the next free sequence within that Sahodaya + year band.
                    $regNo = $generator->format(
                        $generator->yearSuffixForStudent($student, $school),
                        $this->nextSequenceFor($school, $generator, $student),
                    );
                    $generator->applyRegNo($student->fresh(), $regNo);
                    $assigned++;
                    $this->line("  {$student->name} ({$student->id}) → {$regNo}");
                } catch (\Throwable $e) {
                    $this->error("  {$student->name} ({$student->id}): {$e->getMessage()}");
                }
            });

        $this->info("  {$school->name}: assigned {$assigned} student ID(s).");
    }

    private function nextSequenceFor(Tenant $school, StudentRegistrationNumberGenerator $generator, Student $student): int
    {
        $yearSuffix = $generator->yearSuffixForStudent($student, $school);
        $base = StudentRegistrationNumberGenerator::PREFIX.'/'.$yearSuffix.'/';
        $schoolIds = Tenant::where('parent_id', $school->parent_id)->pluck('id')->all() ?: [$school->id];

        $max = Student::whereIn('tenant_id', $schoolIds)
            ->whereNotNull('reg_no')
            ->where('reg_no', 'like', $base.'%')
            ->pluck('reg_no')
            ->map(function (string $value) use ($base) {
                $tail = substr($value, strlen($base));

                return ctype_digit($tail) ? (int) $tail : 0;
            })
            ->max() ?? 0;

        return $max + 1;
    }
}
