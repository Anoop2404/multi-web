<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\Tenant;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;

class CheckUnassignedStudents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'students:check-unassigned
                            {--sahodaya= : Limit check to specific Sahodaya tenant ID}
                            {--school= : Limit check to specific School tenant ID}
                            {--only-unassigned : Only display schools with unassigned students}
                            {--include-withdrawn : Include inactive/withdrawn students}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check count of unassigned students (missing class or house) across all Sahodayas and schools';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sahodayaOpt = $this->option('sahodaya');
        $schoolOpt = $this->option('school');
        $onlyUnassigned = (bool) $this->option('only-unassigned');
        $includeWithdrawn = (bool) $this->option('include-withdrawn');

        $grandTotals = [
            'sahodayas_count'   => 0,
            'schools_count'     => 0,
            'total_students'    => 0,
            'unassigned_class'  => 0,
            'unassigned_house'  => 0,
            'unassigned_either' => 0,
        ];

        $this->info('==================================================================');
        $this->info(' UNASSIGNED STUDENTS AUDIT REPORT');
        $this->info(' Filter: ' . ($includeWithdrawn ? 'All Students (Active + Inactive)' : 'Active Students Only'));
        $this->info('==================================================================');

        if ($schoolOpt) {
            $school = Tenant::query()->where('type', 'school')->find($schoolOpt);
            if (! $school) {
                $this->error("School tenant with ID '{$schoolOpt}' not found.");

                return self::FAILURE;
            }

            if ($school->parent_id) {
                $sahodaya = Tenant::query()->find($school->parent_id);
                if ($sahodaya) {
                    $this->auditSingleSahodaya($sahodaya, [$school], $onlyUnassigned, $includeWithdrawn, $grandTotals);
                } else {
                    $this->auditSchoolsCentral([$school], $onlyUnassigned, $includeWithdrawn, $grandTotals);
                }
            } else {
                $this->auditSchoolsCentral([$school], $onlyUnassigned, $includeWithdrawn, $grandTotals);
            }

            $this->printGrandTotals($grandTotals);

            return self::SUCCESS;
        }

        $sahodayasQuery = Tenant::query()->sahodayas();
        if ($sahodayaOpt) {
            $sahodayasQuery->whereKey($sahodayaOpt);
        }

        $sahodayas = $sahodayasQuery->orderBy('name')->get();

        if ($sahodayas->isEmpty() && $sahodayaOpt) {
            $this->error("No Sahodaya found matching ID '{$sahodayaOpt}'.");

            return self::FAILURE;
        }

        foreach ($sahodayas as $sahodaya) {
            $schools = Tenant::query()
                ->where('type', 'school')
                ->where('parent_id', $sahodaya->id)
                ->orderBy('name')
                ->get();

            if ($schools->isEmpty()) {
                continue;
            }

            $this->auditSingleSahodaya($sahodaya, $schools, $onlyUnassigned, $includeWithdrawn, $grandTotals);
        }

        // Handle standalone schools (schools with no parent_id or invalid parent)
        if (! $sahodayaOpt) {
            $standaloneSchools = Tenant::query()
                ->where('type', 'school')
                ->where(function ($q) {
                    $q->whereNull('parent_id')->orWhereNotIn('parent_id', Tenant::query()->sahodayas()->pluck('id'));
                })
                ->orderBy('name')
                ->get();

            if ($standaloneSchools->isNotEmpty()) {
                $this->info('==================================================================');
                $this->info(' Standalone / Non-affiliated Schools (Central Database)');
                $this->info('==================================================================');
                $this->auditSchoolsCentral($standaloneSchools, $onlyUnassigned, $includeWithdrawn, $grandTotals);
            }
        }

        $this->printGrandTotals($grandTotals);

        return self::SUCCESS;
    }

    private function auditSingleSahodaya(
        Tenant $sahodaya,
        $schools,
        bool $onlyUnassigned,
        bool $includeWithdrawn,
        array &$grandTotals
    ): void {
        try {
            if (TenancyDatabase::enabled()) {
                TenancyDatabase::withTenantDatabase($sahodaya, function () use ($sahodaya, $schools, $onlyUnassigned, $includeWithdrawn, &$grandTotals) {
                    $this->auditSahodayaSchools($sahodaya, $schools, $onlyUnassigned, $includeWithdrawn, $grandTotals);
                });
            } else {
                $this->auditSahodayaSchools($sahodaya, $schools, $onlyUnassigned, $includeWithdrawn, $grandTotals);
            }
        } catch (\Throwable $e) {
            $this->warn("⚠️  Could not query database for Sahodaya {$sahodaya->name} ({$sahodaya->id}): {$e->getMessage()}");
        }
    }

    private function auditSahodayaSchools(
        Tenant $sahodaya,
        $schools,
        bool $onlyUnassigned,
        bool $includeWithdrawn,
        array &$grandTotals
    ): void {
        $tableRows = [];
        $sTotal = 0;
        $sUnassignedClass = 0;
        $sUnassignedHouse = 0;
        $sUnassignedEither = 0;
        $schoolsAuditedCount = 0;

        foreach ($schools as $school) {
            $query = Student::query()->where('tenant_id', $school->id);
            if (! $includeWithdrawn) {
                $query->active();
            }

            $total = (clone $query)->count();
            $unassignedClass = (clone $query)->whereNull('school_class_id')->count();
            $unassignedHouse = (clone $query)->whereNull('school_house_id')->count();
            $unassignedEither = (clone $query)->where(function ($q) {
                $q->whereNull('school_class_id')->orWhereNull('school_house_id');
            })->count();

            if ($onlyUnassigned && $unassignedEither === 0) {
                continue;
            }

            $schoolsAuditedCount++;
            $sTotal += $total;
            $sUnassignedClass += $unassignedClass;
            $sUnassignedHouse += $unassignedHouse;
            $sUnassignedEither += $unassignedEither;

            $tableRows[] = [
                'School ID'         => $school->id,
                'School Name'       => $school->name,
                'Total Students'    => number_format($total),
                'Unassigned Class'  => $unassignedClass > 0 ? "⚠️  {$unassignedClass}" : '0',
                'Unassigned House'  => $unassignedHouse > 0 ? "⚠️  {$unassignedHouse}" : '0',
                'Unassigned Either' => $unassignedEither > 0 ? "❌ {$unassignedEither}" : '0',
            ];
        }

        if (empty($tableRows) && $onlyUnassigned) {
            return;
        }

        $grandTotals['sahodayas_count']++;
        $grandTotals['schools_count'] += $schoolsAuditedCount;
        $grandTotals['total_students'] += $sTotal;
        $grandTotals['unassigned_class'] += $sUnassignedClass;
        $grandTotals['unassigned_house'] += $sUnassignedHouse;
        $grandTotals['unassigned_either'] += $sUnassignedEither;

        $this->line('');
        $this->info("Sahodaya: {$sahodaya->name} ({$sahodaya->id})");
        $this->line(str_repeat('-', 80));

        if (! empty($tableRows)) {
            $this->table(
                ['School ID', 'School Name', 'Total Students', 'Unassigned Class', 'Unassigned House', 'Unassigned Either'],
                $tableRows
            );
        }

        $this->comment("Summary for {$sahodaya->name}: Total: {$sTotal} | Unassigned Class: {$sUnassignedClass} | Unassigned House: {$sUnassignedHouse} | Unassigned Either: {$sUnassignedEither}");
    }

    private function auditSchoolsCentral(
        $schools,
        bool $onlyUnassigned,
        bool $includeWithdrawn,
        array &$grandTotals
    ): void {
        $tableRows = [];
        $sTotal = 0;
        $sUnassignedClass = 0;
        $sUnassignedHouse = 0;
        $sUnassignedEither = 0;
        $schoolsAuditedCount = 0;

        foreach ($schools as $school) {
            $query = Student::query()->where('tenant_id', $school->id);
            if (! $includeWithdrawn) {
                $query->active();
            }

            $total = (clone $query)->count();
            $unassignedClass = (clone $query)->whereNull('school_class_id')->count();
            $unassignedHouse = (clone $query)->whereNull('school_house_id')->count();
            $unassignedEither = (clone $query)->where(function ($q) {
                $q->whereNull('school_class_id')->orWhereNull('school_house_id');
            })->count();

            if ($onlyUnassigned && $unassignedEither === 0) {
                continue;
            }

            $schoolsAuditedCount++;
            $sTotal += $total;
            $sUnassignedClass += $unassignedClass;
            $sUnassignedHouse += $unassignedHouse;
            $sUnassignedEither += $unassignedEither;

            $tableRows[] = [
                'School ID'         => $school->id,
                'School Name'       => $school->name,
                'Total Students'    => number_format($total),
                'Unassigned Class'  => $unassignedClass > 0 ? "⚠️  {$unassignedClass}" : '0',
                'Unassigned House'  => $unassignedHouse > 0 ? "⚠️  {$unassignedHouse}" : '0',
                'Unassigned Either' => $unassignedEither > 0 ? "❌ {$unassignedEither}" : '0',
            ];
        }

        if (! empty($tableRows)) {
            $grandTotals['schools_count'] += $schoolsAuditedCount;
            $grandTotals['total_students'] += $sTotal;
            $grandTotals['unassigned_class'] += $sUnassignedClass;
            $grandTotals['unassigned_house'] += $sUnassignedHouse;
            $grandTotals['unassigned_either'] += $sUnassignedEither;

            $this->table(
                ['School ID', 'School Name', 'Total Students', 'Unassigned Class', 'Unassigned House', 'Unassigned Either'],
                $tableRows
            );
        }
    }

    private function printGrandTotals(array $grandTotals): void
    {
        $this->line('');
        $this->info('==================================================================');
        $this->info(' GRAND TOTAL SUMMARY');
        $this->info('==================================================================');
        $this->line("• Sahodayas Audited       : {$grandTotals['sahodayas_count']}");
        $this->line("• Schools Audited         : {$grandTotals['schools_count']}");
        $this->line('• Total Students          : ' . number_format($grandTotals['total_students']));

        $classPct = $grandTotals['total_students'] > 0
            ? round(($grandTotals['unassigned_class'] / $grandTotals['total_students']) * 100, 1)
            : 0;
        $housePct = $grandTotals['total_students'] > 0
            ? round(($grandTotals['unassigned_house'] / $grandTotals['total_students']) * 100, 1)
            : 0;
        $eitherPct = $grandTotals['total_students'] > 0
            ? round(($grandTotals['unassigned_either'] / $grandTotals['total_students']) * 100, 1)
            : 0;

        $this->line('• Unassigned Class Count  : ' . number_format($grandTotals['unassigned_class']) . " ({$classPct}%)");
        $this->line('• Unassigned House Count  : ' . number_format($grandTotals['unassigned_house']) . " ({$housePct}%)");
        $this->line('• Unassigned Either Count : ' . number_format($grandTotals['unassigned_either']) . " ({$eitherPct}%)");
        $this->info('==================================================================');
    }
}
