<?php

namespace App\Console\Commands;

use App\Models\FestEvent;
use App\Models\Tenant;
use App\Services\Events\PromoteSportsHeadsToDisciplineEvents;
use Illuminate\Console\Command;

class PromoteSportsHeadsToDisciplineEventsCommand extends Command
{
    protected $signature = 'fest:promote-sports-heads
        {--sahodaya= : Sahodaya tenant id or subdomain}
        {--event= : Season fest_events id}
        {--dry-run : Preview without writing}';

    protected $description = 'Promote Sports Event Heads into separate discipline FestEvents under the season hub';

    public function handle(PromoteSportsHeadsToDisciplineEvents $promoter): int
    {
        $dryRun = (bool) $this->option('dry-run');
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
                $tenant->run(function () use ($tenant, $promoter, $dryRun, $eventId) {
                    $this->info(($dryRun ? '[dry-run] ' : '')."Tenant {$tenant->id} ({$tenant->subdomain})");

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
                        $rows = $promoter->promote($season, $dryRun);
                        $this->table(
                            ['head_id', 'head_name', 'event_id', 'title', 'note'],
                            collect($rows)->map(fn ($r) => [
                                $r['head_id'],
                                $r['head_name'],
                                $r['event_id'] ?: '—',
                                $r['title'],
                                isset($r['skipped']) ? 'already linked' : (isset($r['dry_run']) ? 'would create' : 'created'),
                            ])->all(),
                        );
                    }
                });
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$tenant->name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
