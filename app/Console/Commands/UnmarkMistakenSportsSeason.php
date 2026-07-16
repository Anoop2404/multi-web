<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Fixes a standalone "Head = Event" sport event that got mistakenly tagged as a
 * season hub (partition_role = 'sports_season') by FestSportsEventSyncService,
 * because it happened to have an Event Head row. Once tagged, the sync loop
 * auto-creates a duplicate child discipline event (e.g. a second, empty "Chess"
 * under "CHESS 2026"), and every page visit that re-runs the sync
 * (results/reports/catalog pages call syncEventHeads() -> syncSeason(...,
 * createMissing: true)) risks recreating it. This also causes
 * FestEventController::redirectSportsSeasonToHub() to bounce admins away from
 * the Items page back to the Sports Meet hub, since it thinks children exist.
 *
 * Safe by construction: only ever deletes a child event that has zero
 * registrations (mirrors the guard already in FestEventController::destroy()),
 * and only resets partition_role — never touches the parent's own items,
 * registrations, or fees.
 */
class UnmarkMistakenSportsSeason extends Command
{
    protected $signature = 'fest:unmark-mistaken-season
        {event : fest_events id of the standalone sport event mistakenly tagged as a season}
        {--sahodaya= : Sahodaya tenant id or subdomain}
        {--delete-empty-children : Also delete child discipline events that have zero registrations}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Reset a standalone sport event wrongly tagged partition_role=sports_season, optionally deleting its empty duplicate child(ren)';

    public function handle(): int
    {
        $eventId = (int) $this->argument('event');
        $sahodayaOpt = $this->option('sahodaya');
        $dryRun = (bool) $this->option('dry-run');
        $deleteEmpty = (bool) $this->option('delete-empty-children');

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
            ->get();

        if ($tenants->isEmpty()) {
            $this->error('No matching Sahodaya tenant(s). Pass --sahodaya to narrow the search.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $found = false;

            try {
                $tenant->run(function () use ($eventId, $dryRun, $deleteEmpty, $tenant, &$found) {
                    $event = FestEvent::find($eventId);
                    if (! $event) {
                        return;
                    }
                    $found = true;

                    $this->info("Sahodaya: {$tenant->name} ({$tenant->id})");
                    $this->line("  Event #{$event->id}: {$event->title} (partition_role={$event->partition_role}, parent_event_id={$event->parent_event_id})");

                    if ($event->event_type !== 'sports' || $event->parent_event_id !== null) {
                        $this->warn('  Not a top-level sports event — nothing to do.');

                        return;
                    }

                    $children = FestEvent::where('parent_event_id', $event->id)->withCount('registrations')->get();

                    foreach ($children as $child) {
                        $this->line("  Child #{$child->id}: {$child->title} — {$child->registrations_count} registration(s)");
                    }

                    $emptyChildren = $children->filter(fn (FestEvent $c) => $c->registrations_count === 0);
                    $busyChildren = $children->filter(fn (FestEvent $c) => $c->registrations_count > 0);

                    if ($busyChildren->isNotEmpty()) {
                        $this->warn(
                            '  '.$busyChildren->count().' child event(s) have real registrations — not touching partition_role '
                            .'or deleting anything. This event is probably a genuine season hub; investigate further before running this again.'
                        );

                        return;
                    }

                    if ($emptyChildren->isNotEmpty()) {
                        if ($deleteEmpty) {
                            foreach ($emptyChildren as $child) {
                                $this->line("  ".($dryRun ? 'Would delete' : 'Deleting')." empty child #{$child->id}: {$child->title}");
                                if (! $dryRun) {
                                    $child->delete();
                                }
                            }
                        } else {
                            $this->line('  '.$emptyChildren->count().' empty child event(s) found — pass --delete-empty-children to remove them.');
                        }
                    }

                    if ($event->partition_role === 'sports_season') {
                        $this->line('  '.($dryRun ? 'Would reset' : 'Resetting')." partition_role to null on event #{$event->id}.");
                        if (! $dryRun) {
                            $event->update(['partition_role' => null]);
                        }
                    } else {
                        $this->line("  partition_role is already '{$event->partition_role}' — nothing to reset.");
                    }

                    if ($event->nav_hidden && $emptyChildren->isNotEmpty() && ($deleteEmpty || $dryRun)) {
                        $this->line('  '.($dryRun ? 'Would also unhide' : 'Unhiding')." event #{$event->id} from school nav (nav_hidden was set for a season hub).");
                        if (! $dryRun) {
                            $event->update(['nav_hidden' => false]);
                        }
                    }
                });
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$tenant->name}: {$e->getMessage()}");
            } finally {
                if (function_exists('tenancy') && tenancy()->initialized) {
                    tenancy()->end();
                }
            }

            if ($found) {
                return self::SUCCESS;
            }
        }

        $this->error("Event #{$eventId} not found in any checked Sahodaya database.");

        return self::FAILURE;
    }
}
