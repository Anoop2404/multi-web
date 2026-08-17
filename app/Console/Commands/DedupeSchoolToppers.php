<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Topper;
use App\Models\TopperSubjectMark;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DedupeSchoolToppers extends Command
{
    protected $signature = 'board-results:dedupe-toppers 
                            {school? : School Tenant ID, prefix, or name} 
                            {--dry-run : Preview duplicate rows without deleting}';

    protected $description = 'Find and remove duplicate topper entries for a school (keeping the most complete/recent entry for each student).';

    public function handle(): int
    {
        $input = trim((string) $this->argument('school'));
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn("=== DRY RUN MODE: PREVIEWING DUPLICATE TOPPERS ONLY ===\n");
        } else {
            $this->info("=== LIVE MODE: REMOVING DUPLICATE TOPPER RECORDS ===\n");
        }

        // Find school tenant
        $school = Tenant::where('id', $input)
            ->orWhere('school_prefix', $input)
            ->orWhere('data->name', 'like', "%{$input}%")
            ->first();

        if (! $school) {
            $parentTenants = Tenant::whereNull('parent_id')->orWhere('type', 'sahodaya')->get();
            foreach ($parentTenants as $pt) {
                try {
                    tenancy()->initialize($pt);
                    $candidate = Tenant::where('id', $input)
                        ->orWhere('school_prefix', $input)
                        ->orWhere('name', 'like', "%{$input}%")
                        ->first();
                    if ($candidate) {
                        $school = $candidate;
                        break;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        if (! $school) {
            $this->error("School matching '{$input}' could not be found.");
            return self::FAILURE;
        }

        $sahodayaId = $school->parent_id;
        if ($sahodayaId) {
            $sahodaya = Tenant::find($sahodayaId);
            if (! $sahodaya) {
                config(['database.default' => 'central']);
                $sahodaya = Tenant::find($sahodayaId);
            }
            if ($sahodaya) {
                try {
                    tenancy()->initialize($sahodaya);
                } catch (\Throwable $e) {
                    $this->warn("Tenancy initialization warning: " . $e->getMessage());
                }
            }
        }

        $schoolName = $school->data['name'] ?? $school->name ?? $school->id;
        $this->info("Scanning duplicate toppers for: {$schoolName} ({$school->id})\n");

        // Find duplicate topper groups by (board_result_id, entry_type, roll_no) or (tenant_id, entry_type, roll_no)
        $allToppers = Topper::where('tenant_id', $school->id)
            ->whereNotNull('roll_no')
            ->where('roll_no', '!=', '')
            ->get();

        $grouped = $allToppers->groupBy(function (Topper $t) {
            return ($t->board_result_id ?? 'null') . '|' . ($t->entry_type ?? 'overall') . '|' . trim((string) $t->roll_no);
        });

        $duplicatesToDelete = [];
        $previewRows = [];

        foreach ($grouped as $key => $group) {
            if ($group->count() > 1) {
                // Keep the record with highest ID / most marks
                $sorted = $group->sortByDesc('id')->values();
                $keep = $sorted->first();
                $removeList = $sorted->slice(1);

                foreach ($removeList as $remove) {
                    $duplicatesToDelete[] = $remove->id;
                    $previewRows[] = [
                        $remove->id,
                        $remove->name ?? $remove->student_name ?? 'N/A',
                        $remove->roll_no,
                        $remove->entry_type ?? 'overall',
                        "Result ID #{$remove->board_result_id}",
                        "Duplicate of ID #{$keep->id} (Kept)",
                    ];
                }
            }
        }

        if (empty($duplicatesToDelete)) {
            $this->info("No exact duplicate topper records found for {$schoolName}!");
            return self::SUCCESS;
        }

        $this->warn("Found " . count($duplicatesToDelete) . " duplicate topper record(s):");
        $headers = ['Topper ID', 'Student Name', 'Roll No', 'Entry Type', 'Board Result ID', 'Action'];
        $this->table($headers, $previewRows);

        if (! $dryRun) {
            DB::transaction(function () use ($duplicatesToDelete) {
                TopperSubjectMark::whereIn('topper_id', $duplicatesToDelete)->delete();
                Topper::whereIn('id', $duplicatesToDelete)->delete();
            });
            $this->info("\nSuccessfully deleted " . count($duplicatesToDelete) . " duplicate topper record(s)!");
        } else {
            $this->warn("\nDRY RUN COMPLETE: Re-run without --dry-run to delete these duplicate records.");
        }

        return self::SUCCESS;
    }
}
