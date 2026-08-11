<?php

namespace App\Console\Commands\State;

use App\Models\FestEvent;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramPropagation;
use App\Models\Tenant;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;

class CleanupStateDuplicateEvents extends Command
{
    protected $signature = 'state:cleanup-duplicate-events {--dry-run : Only show what would be deleted}';

    protected $description = 'Clean up empty/orphaned duplicate state event records across Sahodaya tenant databases';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info($dryRun ? '=== DRY RUN MODE ===' : '=== EXECUTING CLEANUP ===');

        // 1. Clean up central FestStateProgram duplicates
        $pilots = FestStateProgram::where('title', 'like', '%(Pilot)%')->get();
        foreach ($pilots as $pilot) {
            $this->warn("Found pilot central program: {$pilot->id} | {$pilot->title}");
            if (! $dryRun) {
                FestStateProgramPropagation::where('state_program_id', $pilot->id)->delete();
                $pilot->items()->delete();
                $pilot->delete();
                $this->info("Deleted central pilot program {$pilot->id}");
            }
        }

        // 2. Clean up tenant databases
        $sahodayas = Tenant::query()->sahodayas()->where('is_active', true)->get();

        foreach ($sahodayas as $sahodaya) {
            try {
                TenancyDatabase::runWhenDatabaseReady($sahodaya, function () use ($sahodaya, $dryRun) {
                    // Find state events in tenant database
                    $stateEvents = FestEvent::query()
                        ->where(function ($q) {
                            $q->where('title', 'like', '%(state)%')
                              ->orWhereNotNull('state_program_id');
                        })
                        ->withCount(['items', 'registrations'])
                        ->get();

                    if ($stateEvents->count() <= 1) {
                        return;
                    }

                    $this->info("Sahodaya [{$sahodaya->name}]: found {$stateEvents->count()} state event(s).");

                    // Group by state_program_id or title base
                    $hasPopulated = $stateEvents->contains(fn ($e) => $e->items_count > 0);

                    foreach ($stateEvents as $event) {
                        // Delete empty duplicate events if a populated event exists, or if duplicate with 0 registrations
                        if ($event->registrations_count === 0 && ($event->items_count === 0 || $hasPopulated)) {
                            // If this is the only one with items, keep it
                            if ($event->items_count > 0 && $stateEvents->where('items_count', '>', 0)->count() === 1) {
                                continue;
                            }

                            $this->warn("  -> Deleting duplicate tenant event: ID {$event->id} | {$event->title} | Items: {$event->items_count} | Regs: {$event->registrations_count}");

                            if (! $dryRun) {
                                $event->items()->delete();
                                $event->delete();
                            }
                        }
                    }
                });
            } catch (\Throwable $e) {
                $this->error("Failed on Sahodaya [{$sahodaya->name}]: " . $e->getMessage());
            }
        }

        $this->info('Cleanup completed.');

        return 0;
    }
}
