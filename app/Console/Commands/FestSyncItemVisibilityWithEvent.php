<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\Tenant;
use App\Services\Events\EventContext;
use App\Services\Events\FestItemResultsService;
use Illuminate\Console\Command;

/**
 * One-off remediation for events unpublished before the fix in
 * FestResultsController::unpublish()/FestPhasePublicationService::unpublishResults():
 * those methods only ever flipped the event-wide results_published flag, never the
 * per-item results_published_at/results_hidden flags an individually-published item
 * carries -- and FestPublicVisibilityService's item-level checks always take an item's
 * own flags over the event-wide one when both are known. So an event unpublished before
 * that fix can still have items stuck "individually published," which the public search
 * page's chest-number/level-registration-number lookup (unlike the TV screen and
 * scoreboard, which hide everything outright while the event is unpublished) will still
 * resolve and reveal names/marks for.
 *
 * Targets a specific event by id, on purpose -- no blind platform-wide sweep, since an
 * event can legitimately be mid-way through an item-by-item publish workflow with the
 * event-wide flag never having been true yet, which must NOT be touched by this command.
 * Only ever acts on an event whose own results_published is currently false.
 *
 * Runs in --dry-run mode by default. Requires --commit to write changes.
 */
class FestSyncItemVisibilityWithEvent extends Command
{
    protected $signature = 'fest:sync-item-visibility
        {--sahodaya= : Sahodaya tenant id or subdomain}
        {--event= : Target fest_events id (required)}
        {--commit : Execute the sync (defaults to dry-run)}';

    protected $description = 'Clear stale individually-published item flags on an event whose event-wide results_published is false -- fixes public leaks left over from unpublishing before the item-cascade fix';

    public function handle(): int
    {
        $sahodayaOpt = $this->option('sahodaya');
        $eventOpt = $this->option('event');
        $commit = (bool) $this->option('commit');

        if (! $eventOpt) {
            $this->error('--event=<fest_events id> is required.');

            return self::FAILURE;
        }

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

        $found = false;
        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($tenant, $eventOpt, $commit, &$found) {
                $event = FestEvent::where('id', $eventOpt)->first();
                if (! $event) {
                    return;
                }
                $found = true;
                $this->syncEvent($tenant, $event, $commit);
            });
        }

        if (! $found) {
            $this->error("Event #{$eventOpt} not found in any matching tenant.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function syncEvent(Tenant $tenant, FestEvent $event, bool $commit): void
    {
        if ($event->results_published) {
            $this->warn("[{$tenant->id}] Event #{$event->id} ({$event->title}) has results_published = true -- this command only acts on unpublished events. Nothing to do.");

            return;
        }

        $publicationEventIds = $event->parent_event_id ? [$event->id] : $event->reportableEventIds();

        $stale = FestEventItem::whereIn('event_id', $publicationEventIds)
            ->where(function ($q) {
                $q->whereNotNull('results_published_at')->orWhere('results_hidden', false);
            })
            ->get(['id', 'title', 'results_published_at', 'results_hidden']);

        if ($stale->isEmpty()) {
            $this->info("[{$tenant->id}] Event #{$event->id} ({$event->title}): no stale item-level publish flags found. Nothing to do.");

            return;
        }

        $stillPublished = $stale->filter(fn (FestEventItem $i) => $i->results_published_at && ! $i->results_hidden);

        $this->line("[{$tenant->id}] Event #{$event->id} ({$event->title}): {$stillPublished->count()} item(s) still individually published despite the event being unpublished:");
        foreach ($stillPublished as $item) {
            $this->line("  - #{$item->id} {$item->title}");
        }

        if ($commit) {
            $affected = app(FestItemResultsService::class)->unpublishItemsForEvents($publicationEventIds);
            EventContext::for($event)->recalculateSchoolPoints();
            $this->info("[{$tenant->id}] Cleared publish flags on {$affected} item row(s) for event #{$event->id}.");
        } else {
            $this->info('  Re-run with --commit to clear these.');
        }
    }
}
