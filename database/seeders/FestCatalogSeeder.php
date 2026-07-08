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
            $sync = $service->ensureSeeded($tenantId, $eventType);
            $this->command?->line(sprintf(
                '  %s: %d new, %d updated, %d heads, %d head links',
                $eventType,
                $sync['created'],
                $sync['updated'],
                $sync['heads_created'] ?? 0,
                $sync['head_links'] ?? 0,
            ));
        }
    }
}
