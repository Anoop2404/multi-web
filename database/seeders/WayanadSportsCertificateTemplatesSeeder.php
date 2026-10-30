<?php

namespace Database\Seeders;

use App\Models\CertificateTemplate;
use App\Models\FestEvent;
use App\Models\Tenant;
use App\Support\TenancyDatabase;
use App\Support\TenantStorage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WayanadSportsCertificateTemplatesSeeder extends Seeder
{
    public function run(?string $tenantId = null, ?int $eventId = null): void
    {
        $tenant = Tenant::where('type', 'sahodaya')->findOrFail($tenantId ?? env('SAHODAYA_UUID', 'b639d1e5-cd18-4dc8-88d5-7bd8e0bbc697'));
        TenancyDatabase::withTenantDatabase($tenant, function () use ($tenant, $eventId) {
            // Require an explicit event: never accidentally configure the empty draft sports meet.
            $event = FestEvent::where('tenant_id', $tenant->id)->where('event_type', 'sports')->findOrFail($eventId ?? (int) env('FEST_EVENT_ID'));
            $presets = require resource_path('certificate-templates/wayanad-sports/templates.php');
            $paths = [];
            foreach (['winner' => 'merit', 'participation' => 'participation'] as $type => $asset) {
                $paths[$type] = "tenants/{$tenant->id}/certificate-backgrounds/wayanad-sports-{$asset}-2026.png";
                if (! Storage::disk(TenantStorage::uploadDisk())->put($paths[$type], file_get_contents(resource_path("certificate-templates/wayanad-sports/{$asset}.png")), ['visibility' => 'private'])) {
                    throw new \RuntimeException('Failed to store sports certificate artwork.');
                }
            }
            DB::transaction(function () use ($tenant, $event, $presets, $paths) {
                foreach ($presets as $type => $preset) {
                    CertificateTemplate::firstOrCreate([
                        'tenant_id' => $tenant->id, 'event_type' => 'sports', 'event_id' => $event->id,
                        'item_id' => null, 'certificate_type' => $type, 'title' => $preset['title'],
                    ], $preset + ['background_path' => $paths[$type]]);
                }
                $owner = $event->usesPhasedRegionalBilling() ? $event->rootEvent() : $event;
                $owner->update(['participation_certificate_mode' => 'per_item']);
            });
            $this->command?->info("Sports merit and per-item participation templates ready for {$event->title}.");
        });
    }
}
