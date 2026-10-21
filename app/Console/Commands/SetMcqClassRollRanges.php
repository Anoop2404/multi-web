<?php

namespace App\Console\Commands;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\Tenant;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetMcqClassRollRanges extends Command
{
    protected $signature = 'mcq:set-class-roll-ranges {tenant} {exam} {--apply : Save numbers; otherwise preview only}';
    protected $description = 'Set LKG 10000, UKG 11000, Classes 1–10 12000–21000 without changing approvals';

    public function handle(): int
    {
        $tenant = Tenant::where('type', 'sahodaya')->findOrFail($this->argument('tenant'));

        return TenancyDatabase::withTenantDatabase($tenant, function () use ($tenant) {
            return DB::transaction(function () use ($tenant) {
                $exam = McqExam::where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($this->argument('exam'));
                $rows = McqRegistration::where('exam_id', $exam->id)->where('status', '!=', 'cancelled')
                    ->with(['school:id,name', 'student:id,name,school_class_id', 'student.schoolClass:id,name'])->get();
                $groups = $rows->groupBy(fn ($row) => $row->student?->schoolClass?->name ?? 'Unassigned');
                $starts = [];
                $table = [];
                foreach ($groups as $class => $group) {
                    $start = self::startForClass($class);
                    if ($start === null) {
                        $this->error("Unrecognized class: {$class}. No changes made.");
                        return self::FAILURE;
                    }
                    $starts[$class] = $start;
                    $table[] = [$class, $group->count(), $start, $start + $group->count() - 1];
                }
                usort($table, fn ($a, $b) => $a[2] <=> $b[2]);
                $this->table(['Class', 'Candidates', 'First roll', 'Last roll'], $table);
                if (! $this->option('apply')) {
                    $this->info('Preview only. Add --apply to save. Class + roll identifies a candidate.');
                    return self::SUCCESS;
                }
                $result = app(\App\Services\Mcq\McqClassRollRangeService::class)->apply($exam, $starts);
                $backup = $result['backup'];
                $this->info('Saved '.$rows->count().' roll numbers. Registration IDs, payment and approval statuses unchanged. Backup: '.$backup);
                return self::SUCCESS;
            });
        });
    }

    public static function startForClass(string $class): ?int
    {
        return \App\Services\Mcq\McqClassRollRangeService::startForClass($class);
    }
}
