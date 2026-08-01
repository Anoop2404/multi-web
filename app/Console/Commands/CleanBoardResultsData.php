<?php

namespace App\Console\Commands;

use App\Models\AcademicYearRecord;
use App\Models\BoardResult;
use App\Models\BoardResultUpload;
use App\Models\Tenant;
use App\Models\Topper;
use App\Models\TopperSubjectMark;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;

class CleanBoardResultsData extends Command
{
    protected $signature = 'board-results:clean-data
                            {--remove-missing-roll-no : Remove toppers/records missing CBSE roll_no}
                            {--dedupe : Deduplicate entries by roll_no, keeping only the most recent entry}
                            {--target-academic-year=2025-26 : Target academic year to merge data into}
                            {--tenant= : Optional Sahodaya tenant ID}
                            {--dry-run : Preview changes without deleting or modifying}';

    protected $description = 'Clean board results data missing CBSE roll_no, deduplicate keeping recent entries, and merge academic year records into target year (e.g. 2025-26)';

    public function handle(): int
    {
        $removeMissingRollNo = $this->option('remove-missing-roll-no') || $this->confirm('Remove topper entries missing CBSE roll_no?', true);
        $dedupe = $this->option('dedupe') || $this->confirm('Deduplicate records by CBSE roll_no keeping only the most recent entry?', true);
        $targetYear = $this->option('target-academic-year') ?: '2025-26';
        $tenantId = $this->option('tenant');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('*** DRY RUN MODE: No data will be modified or deleted ***');
        }

        $sahodayas = Tenant::query()
            ->where('type', 'sahodaya')
            ->when($tenantId, fn ($q) => $q->where('id', $tenantId))
            ->get();

        $totalRemovedToppers = 0;
        $totalDeduplicatedToppers = 0;
        $totalMergedResults = 0;

        foreach ($sahodayas as $sahodaya) {
            TenancyDatabase::withTenantDatabase($sahodaya, function () use ($sahodaya, $removeMissingRollNo, $dedupe, $targetYear, $dryRun, &$totalRemovedToppers, &$totalDeduplicatedToppers, &$totalMergedResults) {
                // 1. Clean missing CBSE roll_no
                if ($removeMissingRollNo) {
                    $invalidToppersQuery = Topper::query()
                        ->where(function ($q) {
                            $q->whereNull('roll_no')
                              ->orWhere('roll_no', '')
                              ->orWhereRaw("TRIM(roll_no) = ''");
                        });

                    $count = $invalidToppersQuery->count();
                    if ($count > 0) {
                        $this->info("{$sahodaya->name}: Found {$count} topper(s) missing CBSE roll_no.");
                        $topperIds = $invalidToppersQuery->pluck('id')->all();
                        $totalRemovedToppers += count($topperIds);
                        if (! $dryRun) {
                            TopperSubjectMark::whereIn('topper_id', $topperIds)->delete();
                            $deleted = Topper::whereIn('id', $topperIds)->delete();
                            $this->info("  -> Deleted {$deleted} invalid topper record(s).");
                        }
                    }
                }

                // 2. Deduplicate entries by roll_no (keeping most recent id)
                if ($dedupe) {
                    $duplicateGroups = Topper::query()
                        ->whereNotNull('roll_no')
                        ->whereRaw("TRIM(roll_no) != ''")
                        ->select('board_result_id', 'entry_type', 'roll_no')
                        ->selectRaw('COUNT(*) as cnt, MAX(id) as max_id')
                        ->groupBy('board_result_id', 'entry_type', 'roll_no')
                        ->havingRaw('COUNT(*) > 1')
                        ->get();

                    $toppersToDelete = [];
                    foreach ($duplicateGroups as $group) {
                        $ids = Topper::query()
                            ->where('board_result_id', $group->board_result_id)
                            ->where('entry_type', $group->entry_type)
                            ->where('roll_no', $group->roll_no)
                            ->where('id', '!=', $group->max_id)
                            ->pluck('id')
                            ->all();

                        $toppersToDelete = array_merge($toppersToDelete, $ids);
                    }

                    if (! empty($toppersToDelete)) {
                        $dupCount = count($toppersToDelete);
                        $this->info("{$sahodaya->name}: Found {$dupCount} duplicate topper record(s) (keeping most recent).");
                        $totalDeduplicatedToppers += $dupCount;
                        if (! $dryRun) {
                            TopperSubjectMark::whereIn('topper_id', $toppersToDelete)->delete();
                            $deletedDups = Topper::whereIn('id', $toppersToDelete)->delete();
                            $this->info("  -> Deleted {$deletedDups} duplicate topper record(s).");
                        }
                    }
                }

                // 3. Merge Academic Years to targetYear (e.g., '2025-26')
                if ($targetYear) {
                    $targetYearRecord = AcademicYearRecord::where('label', $targetYear)->first();
                    $otherResults = BoardResult::query()->where('academic_year', '!=', $targetYear)->get();

                    if ($otherResults->isNotEmpty()) {
                        $this->info("{$sahodaya->name}: Found {$otherResults->count()} board result batch(es) from other academic years to merge into '{$targetYear}'.");

                        if (! $dryRun) {
                            foreach ($otherResults as $oldResult) {
                                $targetResult = BoardResult::where('class', $oldResult->class)
                                    ->where('examination_type', $oldResult->examination_type)
                                    ->where('academic_year', $targetYear)
                                    ->first();

                                if (! $targetResult) {
                                    $oldResult->update([
                                        'academic_year' => $targetYear,
                                        'academic_year_id' => $targetYearRecord?->id,
                                    ]);
                                    $totalMergedResults++;
                                } else {
                                    Topper::where('board_result_id', $oldResult->id)->update([
                                        'board_result_id' => $targetResult->id,
                                    ]);

                                    $maxVersion = (int) BoardResultUpload::where('board_result_id', $targetResult->id)->max('version') ?: 0;
                                    foreach (BoardResultUpload::where('board_result_id', $oldResult->id)->get() as $upload) {
                                        $maxVersion++;
                                        $upload->update([
                                            'board_result_id' => $targetResult->id,
                                            'version' => $maxVersion,
                                        ]);
                                    }

                                    $oldResult->delete();
                                    $totalMergedResults++;
                                }
                            }
                            $this->info("  -> Successfully merged {$otherResults->count()} board result batch(es) to '{$targetYear}'.");
                        }
                    }
                }
            });
        }

        if ($dryRun) {
            $this->warn("Dry run completed. Found {$totalRemovedToppers} record(s) without roll_no, {$totalDeduplicatedToppers} duplicate(s), and {$totalMergedResults} result batch(es) to merge into '{$targetYear}'. No data was modified.");
        } else {
            $this->info("Completed: Removed {$totalRemovedToppers} record(s) without roll_no, deduplicated {$totalDeduplicatedToppers} duplicate(s), and merged {$totalMergedResults} result batch(es) into '{$targetYear}'.");
        }

        return self::SUCCESS;
    }
}
