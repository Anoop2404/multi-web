<?php

namespace App\Console\Commands;

use App\Models\BoardResult;
use App\Models\Tenant;
use App\Models\Topper;
use Illuminate\Console\Command;

class RelinkOrphanToppers extends Command
{
    protected $signature = 'board-results:relink-orphans {school : School Tenant ID, prefix, or name} {--year=2025-26 : Target Academic Year to link orphans to if ambiguous}';

    protected $description = 'Re-link orphaned topper records (whose board_result_id no longer exists) to the active board result records for a school.';

    public function handle(): int
    {
        $input = trim((string) $this->argument('school'));
        $targetYear = $this->option('year') ?: '2025-26';

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
        $this->info("Processing orphan toppers for: {$schoolName} ({$school->id})");

        // Fetch active board results
        $activeResults = BoardResult::where('tenant_id', $school->id)->get();
        if ($activeResults->isEmpty()) {
            $this->error("No active BoardResult records found for this school to link toppers to.");
            return self::FAILURE;
        }

        $activeResultIds = $activeResults->pluck('id')->all();
        $this->info("Active BoardResult IDs: " . implode(', ', $activeResultIds));

        // Find orphan toppers (where board_result_id is not in activeResultIds)
        $orphans = Topper::where('tenant_id', $school->id)
            ->whereNotIn('board_result_id', $activeResultIds)
            ->get();

        if ($orphans->isEmpty()) {
            $this->info("No orphaned toppers found for this school! All toppers are linked properly.");
            return self::SUCCESS;
        }

        $this->info("Found " . $orphans->count() . " orphaned topper record(s). Re-linking...");

        // Map active results by class and academic year
        // Example: class 10 + 2025-26 => Result ID 55
        // Example: class 12 + 2025-26 => Result ID 150
        $class10Result = $activeResults->first(fn ($r) => (int)$r->class === 10 && $r->academic_year === $targetYear)
            ?? $activeResults->first(fn ($r) => (int)$r->class === 10);

        $class12Result = $activeResults->first(fn ($r) => (int)$r->class === 12 && $r->academic_year === $targetYear)
            ?? $activeResults->first(fn ($r) => (int)$r->class === 12);

        $relinkedCount = 0;

        foreach ($orphans as $topper) {
            // Determine if Class 12 (has stream) or Class 10 (no stream / AISSE)
            $isClass12 = filled($topper->stream) && $topper->stream !== 'N/A';

            $targetResult = $isClass12 ? $class12Result : $class10Result;

            if (! $targetResult) {
                $targetResult = $activeResults->first();
            }

            if ($targetResult) {
                $topper->update([
                    'board_result_id' => $targetResult->id,
                ]);
                $relinkedCount++;
                $this->line("  - Linked Topper #{$topper->id} ({$topper->name}) -> Result #{$targetResult->id} (Class {$targetResult->class}, {$targetResult->academic_year})");
            }
        }

        $this->info("\nSuccessfully re-linked {$relinkedCount} orphan topper(s) to active BoardResult records!");

        return self::SUCCESS;
    }
}
