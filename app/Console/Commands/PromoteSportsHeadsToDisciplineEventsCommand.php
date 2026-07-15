<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\Tenant;
use App\Services\Events\FestSportsEventSyncService;
use Illuminate\Console\Command;

/**
 * @deprecated Use fest:migrate-sports-head-to-event. Kept as an alias that syncs sport events.
 */
class PromoteSportsHeadsToDisciplineEventsCommand extends Command
{
    protected $signature = 'fest:promote-sports-heads
        {--sahodaya= : Sahodaya tenant id or subdomain}
        {--event= : Season fest_events id}
        {--dry-run : Preview without writing}';

    protected $description = '[Deprecated] Sync sports catalog into child sport FestEvents (alias of FestSportsEventSyncService)';

    public function handle(FestSportsEventSyncService $sync): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Dry-run: would sync sport events via FestSportsEventSyncService (no writes). Prefer fest:migrate-sports-head-to-event --dry-run for fee migration.');
        }

        $sahodayaOpt = $this->option('sahodaya');
        $eventId = $this->option('event');

        $tenants = Tenant::query()
            ->where('type', 'sahodaya')
            ->when($sahodayaOpt, function ($q) use ($sahodayaOpt) {
                $q->where(function ($inner) use ($sahodayaOpt) {
                    $inner->where('id', $sahodayaOpt)->orWhere('subdomain', $sahodayaOpt);
                });
            })
            ->get();

        if ($tenants->isEmpty()) {
            $this->error('No matching Sahodaya tenants.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($tenant, $sync, $dryRun, $eventId) {
                    $this->info(($dryRun ? '[dry-run] ' : '')."Tenant {$tenant->id}");

                    $seasons = FestEvent::query()
                        ->ofType('sports')
                        ->whereNull('parent_event_id')
                        ->when($eventId, fn ($q) => $q->whereKey($eventId))
                        ->orderBy('id')
                        ->get();

                    if ($seasons->isEmpty()) {
                        $this->line('  No sports season events.');

                        return;
                    }

                    foreach ($seasons as $season) {
                        if ($dryRun) {
                            $this->line("  Would sync season #{$season->id} {$season->title}");

                            continue;
                        }
                        $result = $sync->syncSeason($season);
                        $this->line("  Season #{$season->id}: created {$result['created']}, updated {$result['updated']}");
                    }
                });
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$tenant->name}: {$e->getMessage()}");
            } finally {
                if (function_exists('tenancy') && tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        return self::SUCCESS;
    }
}
