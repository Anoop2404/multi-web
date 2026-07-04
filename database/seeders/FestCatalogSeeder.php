<?php

namespace Database\Seeders;

use App\Services\Events\FestCatalogService;
use Illuminate\Database\Seeder;

/** Seed CKSC master item catalogs (Kalotsav, Sports, Kids Fest, Teacher Fest) for a Sahodaya tenant. */
class FestCatalogSeeder extends Seeder
{
    /** @param  list<string>  $eventTypes */
    public function run(string $tenantId, array $eventTypes = ['kalolsavam', 'sports', 'kids_fest', 'teacher_fest']): void
    {
        $service = app(FestCatalogService::class);

        foreach ($eventTypes as $eventType) {
            $added = $service->ensureSeeded($tenantId, $eventType);
            $this->command?->line("  {$eventType}: {$added} new master catalog items");
            if ($eventType === 'sports') {
                app(\App\Services\Events\FestItemHeadService::class)->ensureCatalogHeads($tenantId, $eventType);
            }
        }
    }
}
