<?php

namespace Database\Seeders;

use App\Models\FestEvent;
use App\Models\Tenant;
use App\Services\Events\FestTrophyService;
use Illuminate\Database\Seeder;

class KochiMetroKalotsavTrophiesSeeder extends Seeder
{
    public function run(): void
    {
        $eventId = (int) env('FEST_EVENT_ID');
        if ($eventId < 1) {
            throw new \InvalidArgumentException('Set FEST_EVENT_ID to the event that should receive the trophy preset.');
        }
        $tenantId = tenant('id') ?? env('SAHODAYA_UUID');
        $tenant = Tenant::where('type', 'sahodaya')
            ->when($tenantId, fn ($q) => $q->whereKey($tenantId), fn ($q) => $q->where('subdomain', 'kochimetro'))
            ->firstOrFail();
        $seed = function () use ($tenant, $eventId) {
            $event = FestEvent::where('tenant_id', $tenant->id)->findOrFail($eventId);
            $count = app(FestTrophyService::class)->seedKochiMetroPreset($event, replace: false);
            $this->command?->info("Added {$count} trophy definitions for {$event->title}; existing trophies preserved.");
        };
        if (tenant('id')) {
            $seed();
        } else {
            $tenant->run($seed);
        }
    }
}
