<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Events\FestItemHeadService;
use Illuminate\Console\Command;

class SyncFestCatalog extends Command
{
    protected $signature = 'fest:sync-catalog
                            {--tenant= : Sync a single Sahodaya tenant id}
                            {--type=* : Event types to sync (default: all program catalogs)}
                            {--skip-events : Skip relinking heads on existing sports fest events}';

    protected $description = 'Re-sync CKSC master catalog items and sports item heads on existing tenant databases';

    /** @var list<string> */
    private const DEFAULT_TYPES = [
        'sports',
        'kalolsavam',
        'kids_fest',
        'teacher_fest',
        'english_fest',
        'science_fest',
    ];

    public function handle(FestItemHeadService $headService): int
    {
        $types = $this->option('type') ?: self::DEFAULT_TYPES;
        $linkEvents = ! $this->option('skip-events');
        $tenantId = $this->option('tenant');

        if ($tenantId) {
            $this->syncTenant(Tenant::query()->where('type', 'sahodaya')->findOrFail($tenantId), $headService, $types, $linkEvents);

            return self::SUCCESS;
        }

        $count = 0;
        Tenant::query()->where('type', 'sahodaya')->orderBy('name')->each(function (Tenant $tenant) use ($headService, $types, $linkEvents, &$count) {
            try {
                $this->syncTenant($tenant, $headService, $types, $linkEvents);
                $count++;
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$tenant->name}: {$e->getMessage()}");
            }
        });

        $this->info("Synced catalog on {$count} Sahodaya tenant(s).");

        return self::SUCCESS;
    }

    /** @param  list<string>  $types */
    private function syncTenant(Tenant $tenant, FestItemHeadService $headService, array $types, bool $linkEvents): void
    {
        $tenant->run(function () use ($tenant, $headService, $types, $linkEvents) {
            $totals = $headService->backfillTenant($tenant->id, $types, $linkEvents);

            $this->line(sprintf(
                '  ✓ %s — %d new, %d updated, %d heads, %d head links, %d sports events',
                $tenant->name,
                $totals['created'],
                $totals['updated'],
                $totals['heads_created'],
                $totals['head_links'],
                $totals['events_synced'],
            ));
        });
    }
}
