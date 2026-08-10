<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\SchoolRegionAssignment;
use App\Models\Tenant;
use App\Support\AcademicYear;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repairs misplaced parent operational rows (docs/REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md §8.2 / §8.3 / Phase 8).
 *
 * Runs in --dry-run mode by default. Requires --commit to write changes.
 */
class FestRepairEventTopology extends Command
{
    protected $signature = 'fest:repair-event-topology
        {--sahodaya= : Sahodaya tenant id or subdomain}
        {--event= : Target root fest_events id}
        {--commit : Execute data relocation writes (defaults to dry-run)}';

    protected $description = 'Repair misplaced parent operational rows by relocating them to matching regional child events (remediation plan §8.3)';

    public function handle(): int
    {
        $sahodayaOpt = $this->option('sahodaya');
        $eventOpt = $this->option('event');
        $commit = (bool) $this->option('commit');

        if (! $commit) {
            $this->info('Running in DRY-RUN mode. Use --commit to apply changes.');
        }

        $tenants = Tenant::query()
            ->where('type', 'sahodaya')
            ->when($sahodayaOpt, function ($q) use ($sahodayaOpt) {
                $q->where('id', $sahodayaOpt)->orWhere('subdomain', $sahodayaOpt);
            })
            ->get();

        if ($tenants->isEmpty()) {
            $this->error('No matching Sahodaya tenants found.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $this->repairTenant($tenant, $eventOpt, $commit);
        }

        return self::SUCCESS;
    }

    private function repairTenant(Tenant $tenant, null|string|int $eventOpt, bool $commit): void
    {
        $roots = FestEvent::query()
            ->where('tenant_id', $tenant->id)
            ->whereNull('parent_event_id')
            ->where('conduct_mode', 'partitioned')
            ->when($eventOpt, fn ($q) => $q->whereKey($eventOpt))
            ->get();

        $year = AcademicYear::forSahodaya($tenant->id);
        $activeAssignments = SchoolRegionAssignment::forTenant($tenant->id)
            ->forYear($year)
            ->get()
            ->keyBy('school_id');

        foreach ($roots as $root) {
            $children = FestEvent::where('parent_event_id', $root->id)
                ->where('partition_role', 'region')
                ->whereNotNull('region_id')
                ->get()
                ->keyBy('region_id');

            $misplacedRegistrations = FestRegistration::where('event_id', $root->id)->get();

            if ($misplacedRegistrations->isEmpty()) {
                continue;
            }

            $this->line("Tenant {$tenant->id} | Event #{$root->id} ('{$root->title}'): Found {$misplacedRegistrations->count()} misplaced parent registration(s).");

            foreach ($misplacedRegistrations as $reg) {
                $assignment = $activeAssignments->get($reg->school_id);

                if (! $assignment) {
                    $this->warn("  Registration #{$reg->id} (school '{$reg->school_id}'): No active-year region assignment found. Quarantining.");

                    continue;
                }

                $child = $children->get($assignment->region_id);

                if (! $child) {
                    $this->warn("  Registration #{$reg->id}: Active region_id=#{$assignment->region_id} has no matching regional child event. Quarantining.");

                    continue;
                }

                $this->info("  Registration #{$reg->id}: Relocating from parent #{$root->id} -> region child #{$child->id} ('{$child->title}').");

                if ($commit) {
                    DB::transaction(function () use ($reg, $child) {
                        $reg->update(['event_id' => $child->id]);

                        DB::table('fest_participants')
                            ->where('registration_id', $reg->id)
                            ->update(['event_id' => $child->id]);
                    });
                }
            }
        }
    }
}
