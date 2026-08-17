<?php

namespace App\Console\Commands;

use App\Models\BoardResult;
use App\Models\Tenant;
use App\Models\Topper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RelinkOrphanToppers extends Command
{
    protected $signature = 'board-results:relink-orphans 
                            {school? : Optional School Tenant ID, prefix, or name. Omit to process ALL schools} 
                            {--dry-run : Preview changes without making any database updates}
                            {--year=2025-26 : Target Academic Year to link orphans to}';

    protected $description = 'Safely inspect (dry-run) and re-link orphaned topper records (whose board_result_id no longer exists) across all schools and tenant databases.';

    public function handle(): int
    {
        $schoolInput = trim((string) $this->argument('school'));
        $dryRun = (bool) $this->option('dry-run');
        $targetYear = (string) ($this->option('year') ?: '2025-26');

        if ($dryRun) {
            $this->warn("=== DRY RUN MODE ENABLED — NO DATABASE CHANGES WILL BE SAVED ===\n");
        } else {
            $this->info("=== LIVE MODE — DATABASE RECORDS WILL BE UPDATED ===\n");
        }

        // Discover all parent tenants / Sahodayas
        $parentTenants = Tenant::whereNull('parent_id')->orWhere('type', 'sahodaya')->get();
        if ($parentTenants->isEmpty()) {
            // Fallback for single DB or default tenant
            $parentTenants = collect([(object)['id' => null, 'name' => 'Central/Default']]);
        }

        $totalOrphansFound = 0;
        $totalRelinked = 0;

        foreach ($parentTenants as $parent) {
            if ($parent->id) {
                try {
                    tenancy()->initialize($parent);
                } catch (\Throwable $e) {
                    $this->warn("Could not initialize tenant {$parent->id}: " . $e->getMessage());
                    continue;
                }
            }

            if (! Schema::hasTable('toppers') || ! Schema::hasTable('board_results')) {
                continue;
            }

            $schoolQuery = Tenant::query();
            if ($schoolInput !== '') {
                $schoolQuery->where(function ($q) use ($schoolInput) {
                    $q->where('id', $schoolInput)
                        ->orWhere('school_prefix', $schoolInput)
                        ->orWhere('name', 'like', "%{$schoolInput}%")
                        ->orWhere('data', 'like', "%{$schoolInput}%");
                });
            }

            $schools = $schoolQuery->get();
            if ($schools->isEmpty() && $schoolInput !== '') {
                continue;
            }

            foreach ($schools as $school) {
                $schoolName = $school->data['name'] ?? $school->name ?? $school->id;
                $activeResults = BoardResult::where('tenant_id', $school->id)->get();
                
                if ($activeResults->isEmpty()) {
                    continue;
                }

                $activeResultIds = $activeResults->pluck('id')->all();
                $orphans = Topper::where('tenant_id', $school->id)
                    ->whereNotIn('board_result_id', $activeResultIds)
                    ->get();

                if ($orphans->isEmpty()) {
                    continue;
                }

                $totalOrphansFound += $orphans->count();

                $this->info("------------------------------------------------------------------");
                $this->info("School: {$schoolName} ({$school->id})");
                $this->info("Found " . $orphans->count() . " orphaned topper record(s). Active BoardResults: " . implode(', ', $activeResultIds));

                // Group active results by class
                $class10Result = $activeResults->first(fn ($r) => (int)$r->class === 10 && $r->academic_year === $targetYear)
                    ?? $activeResults->first(fn ($r) => (int)$r->class === 10);

                $class12Result = $activeResults->first(fn ($r) => (int)$r->class === 12 && $r->academic_year === $targetYear)
                    ?? $activeResults->first(fn ($r) => (int)$r->class === 12);

                $previewRows = [];

                foreach ($orphans as $topper) {
                    $isClass12 = filled($topper->stream) && $topper->stream !== 'N/A';
                    $targetResult = $isClass12 ? $class12Result : $class10Result;
                    if (! $targetResult) {
                        $targetResult = $activeResults->first();
                    }

                    $actionStr = $dryRun ? '[DRY RUN] Would link to' : 'Linked to';

                    if ($targetResult) {
                        $previewRows[] = [
                            $topper->id,
                            $topper->name ?? $topper->student_name ?? 'N/A',
                            $topper->entry_type ?? 'overall',
                            "ID #{$topper->board_result_id} (Deleted)",
                            "{$actionStr} ID #{$targetResult->id} (Class {$targetResult->class}, {$targetResult->academic_year})",
                        ];

                        if (! $dryRun) {
                            $topper->update([
                                'board_result_id' => $targetResult->id,
                            ]);
                            $totalRelinked++;
                        }
                    }
                }

                $headers = ['Topper ID', 'Student Name', 'Entry Type', 'Current Result ID', 'Target Result Action'];
                $this->table($headers, $previewRows);
            }
        }

        $this->info("\n=================================================");
        if ($dryRun) {
            $this->warn("DRY RUN COMPLETE: Found {$totalOrphansFound} orphaned topper record(s) across all schools.");
            $this->info("To execute the re-linking and update the database, re-run without --dry-run:");
            $this->info("php artisan board-results:relink-orphans " . ($schoolInput ? $schoolInput : ''));
        } else {
            $this->info("RE-LINKING COMPLETE: Successfully re-linked {$totalRelinked} of {$totalOrphansFound} orphaned topper record(s)!");
        }
        $this->info("=================================================\n");

        return self::SUCCESS;
    }
}
