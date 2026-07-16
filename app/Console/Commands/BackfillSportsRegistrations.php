<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestItemHead;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use App\Models\Tenant;
use App\Services\Events\FestSportsEventSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repairs sports data stranded by the Head = Event transition:
 *
 * 1. Registrations (+ participants, marks, schedules) whose event_id still points
 *    at the season hub while their item was moved onto a sport event. The page-load
 *    sync used to move items WITHOUT their registrations, making a school's existing
 *    registrations invisible on the per-sport pages.
 * 2. Custom (non-catalog) Event Heads that never got a sport event of their own.
 * 3. Season hubs left visible to schools even though per-sport children exist.
 *
 * Idempotent — re-running finds nothing to do. Fee-row consolidation stays in
 * fest:migrate-sports-head-to-event (run that after this when fees look wrong).
 */
class BackfillSportsRegistrations extends Command
{
    protected $signature = 'fest:backfill-sports-registrations
        {--sahodaya= : Sahodaya tenant id or subdomain}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Remap stranded sports registrations to their sport events, promote custom heads, hide season hubs';

    public function handle(FestSportsEventSyncService $sync): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sahodayaOpt = $this->option('sahodaya');

        if ($dryRun) {
            $this->warn('Dry run — no changes will be saved.');
        }

        $tenants = Tenant::query()
            ->where('type', 'sahodaya')
            ->when($sahodayaOpt, function ($q) use ($sahodayaOpt) {
                $q->where(function ($inner) use ($sahodayaOpt) {
                    $inner->where('id', $sahodayaOpt)->orWhere('subdomain', $sahodayaOpt);
                });
            })
            ->orderBy('name')
            ->get();

        if ($tenants->isEmpty()) {
            $this->error('No matching Sahodaya tenants.');

            return self::FAILURE;
        }

        $totals = ['registrations' => 0, 'participants' => 0, 'marks' => 0, 'schedules' => 0, 'heads_promoted' => 0, 'hubs_hidden' => 0];

        foreach ($tenants as $tenant) {
            $this->info("Sahodaya: {$tenant->name} ({$tenant->id})");

            try {
                $tenant->run(function () use ($sync, $dryRun, &$totals) {
                    $result = $this->backfillTenantDb($sync, $dryRun);
                    foreach ($result as $k => $v) {
                        $totals[$k] += $v;
                    }
                });
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$tenant->name}: {$e->getMessage()}");
            } finally {
                // A failed initialize() can leave tenancy dangling on a broken tenant DB.
                if (function_exists('tenancy') && tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        $this->newLine();
        $verb = $dryRun ? 'Would fix' : 'Fixed';
        $this->info(
            "{$verb}: {$totals['registrations']} registration(s), {$totals['participants']} participant(s), "
            ."{$totals['marks']} mark(s), {$totals['schedules']} schedule row(s); "
            ."{$totals['heads_promoted']} custom head(s) promoted; {$totals['hubs_hidden']} season hub(s) hidden."
        );

        return self::SUCCESS;
    }

    /** @return array{registrations: int, participants: int, marks: int, schedules: int, heads_promoted: int, hubs_hidden: int} */
    private function backfillTenantDb(FestSportsEventSyncService $sync, bool $dryRun): array
    {
        $stats = ['registrations' => 0, 'participants' => 0, 'marks' => 0, 'schedules' => 0, 'heads_promoted' => 0, 'hubs_hidden' => 0];

        $seasons = FestEvent::query()
            ->where('event_type', 'sports')
            ->whereNull('parent_event_id')
            ->whereNull('conducting_school_id')
            ->orderBy('id')
            ->get()
            ->filter(fn (FestEvent $e) => $e->partition_role === 'sports_season'
                || FestEvent::where('parent_event_id', $e->id)->exists()
                || FestItemHead::forTenant($e->tenant_id)->where('event_id', $e->id)->exists());

        foreach ($seasons as $season) {
            $this->line("  Season: {$season->title} (#{$season->id})");

            // 1. Promote custom heads so their items/registrations have a target event.
            $pendingCustomHeads = FestItemHead::forTenant($season->tenant_id)
                ->where('event_id', $season->id)
                ->whereNull('discipline_event_id')
                ->whereNull('parent_id')
                ->whereNull('catalog_key')
                ->count();

            if ($pendingCustomHeads > 0) {
                if ($dryRun) {
                    $this->line("    Would promote {$pendingCustomHeads} custom head(s) to sport events.");
                    $stats['heads_promoted'] += $pendingCustomHeads;
                } else {
                    $stats['heads_promoted'] += $sync->promoteCustomHeads($season);
                }
            }

            // 2. Remap registrations stranded on the season whose item moved to a child.
            $strays = FestRegistration::query()
                ->where('fest_registrations.event_id', $season->id)
                ->join('fest_event_items', 'fest_event_items.id', '=', 'fest_registrations.item_id')
                ->where('fest_event_items.event_id', '!=', $season->id)
                ->select('fest_registrations.id as reg_id', 'fest_event_items.event_id as sport_event_id')
                ->get()
                ->groupBy('sport_event_id');

            foreach ($strays as $sportEventId => $group) {
                $regIds = $group->pluck('reg_id')->map(fn ($id) => (int) $id)->all();
                $sport = FestEvent::find($sportEventId);
                $label = $sport?->title ?? "event #{$sportEventId}";
                $this->line('    '.count($regIds)." registration(s) → {$label}");

                if ($dryRun) {
                    $stats['registrations'] += count($regIds);
                    $stats['participants'] += FestParticipant::whereIn('registration_id', $regIds)->count();

                    continue;
                }

                DB::transaction(function () use ($season, $sportEventId, $regIds, &$stats) {
                    $stats['registrations'] += FestRegistration::whereIn('id', $regIds)
                        ->update(['event_id' => $sportEventId]);

                    $stats['participants'] += FestParticipant::whereIn('registration_id', $regIds)
                        ->where(fn ($q) => $q->where('event_id', $season->id)->orWhereNull('event_id'))
                        ->update(['event_id' => $sportEventId]);
                });
            }

            // 3. Marks / schedule rows still keyed to the season for moved items.
            foreach ([FestMark::class => 'marks', FestSchedule::class => 'schedules'] as $model => $key) {
                $table = (new $model)->getTable();

                $rows = $model::query()
                    ->where("{$table}.event_id", $season->id)
                    ->join('fest_event_items', 'fest_event_items.id', '=', "{$table}.item_id")
                    ->where('fest_event_items.event_id', '!=', $season->id)
                    ->select("{$table}.id as row_id", 'fest_event_items.event_id as sport_event_id')
                    ->get()
                    ->groupBy('sport_event_id');

                foreach ($rows as $sportEventId => $group) {
                    if ($dryRun) {
                        $stats[$key] += $group->count();

                        continue;
                    }

                    $stats[$key] += $model::whereIn('id', $group->pluck('row_id'))
                        ->update(['event_id' => $sportEventId]);
                }
            }

            // 4. Hide the hub from schools once children exist.
            if (! $season->nav_hidden && FestEvent::where('parent_event_id', $season->id)->exists()) {
                if ($dryRun) {
                    $this->line('    Would hide season hub from school nav.');
                } else {
                    $season->update(['nav_hidden' => true]);
                }
                $stats['hubs_hidden']++;
            }
        }

        if ($seasons->isEmpty()) {
            $this->line('  No sports season hubs.');
        }

        return $stats;
    }
}
