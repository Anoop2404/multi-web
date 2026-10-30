<?php

namespace Database\Seeders;

use App\Models\CertificateTemplate;
use App\Models\FestEvent;
use App\Models\Tenant;
use App\Support\TenancyDatabase;
use App\Support\TenantStorage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class KochiKalotsavCertificateTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('type', 'sahodaya')->findOrFail(env('SAHODAYA_UUID', 'd4db851f-d9df-496f-b2fc-83747182b8ff'));
        TenancyDatabase::withTenantDatabase($tenant, function () use ($tenant) {
            $event = FestEvent::where('tenant_id', $tenant->id)->findOrFail((int) env('FEST_EVENT_ID', 2));
            $preset = require resource_path('certificate-templates/kochi/merit.php');
            $path = "tenants/{$tenant->id}/certificate-backgrounds/kochi-utsav-2026-merit.png";
            if (! Storage::disk(TenantStorage::uploadDisk())->put($path, file_get_contents(resource_path('certificate-templates/kochi/utsav-2026-merit.png')), ['visibility' => 'private'])) {
                throw new \RuntimeException('Failed to store Kochi certificate artwork.');
            }
            $template = CertificateTemplate::firstOrCreate([
                'tenant_id' => $tenant->id, 'event_type' => 'fest', 'event_id' => $event->id,
                'item_id' => null, 'certificate_type' => 'winner', 'title' => $preset['title'],
            ], $preset + ['background_path' => $path]);
            $this->command?->info("Kochi merit template #{$template->id} ready for {$event->title}.");
        });
    }
}
