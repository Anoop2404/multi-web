<?php

namespace Database\Seeders;

use App\Models\CertificateTemplate;
use App\Models\FestEvent;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class MalabarKalotsavCertificateTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = tenant('id') ?? env('SAHODAYA_UUID');
        $tenant = Tenant::where('type', 'sahodaya')
            ->when($tenantId, fn ($query) => $query->where('id', $tenantId),
                fn ($query) => $query->whereIn('subdomain', ['malabar', 'malabarsahodaya']))
            ->firstOrFail();

        $seed = function () use ($tenant): void {
            $eventId = env('FEST_EVENT_ID');
            $event = $eventId
                ? FestEvent::where('tenant_id', $tenant->id)->findOrFail($eventId)
                : null;
            $base = "tenants/{$tenant->id}/certificate-backgrounds/malabar-kalotsav-2026-merit";
            foreach (['png'] as $extension) {
                $source = resource_path("certificate-templates/malabar/kalotsav-2026-merit.{$extension}");
                if (! Storage::disk('shared')->put("{$base}.{$extension}", file_get_contents($source))) {
                    throw new \RuntimeException('Failed to store Malabar certificate artwork.');
                }
            }
            // Browser previews use /storage; the shared private copy serves embedded PDFs.
            $public = Storage::build(['driver' => 'local', 'root' => base_path('storage/app/public')]);
            if (!$public->put("{$base}.png", file_get_contents(resource_path('certificate-templates/malabar/kalotsav-2026-merit.png')))) {
                throw new \RuntimeException('Failed to store public Malabar certificate artwork.');
            }
            foreach (['merit', 'participation'] as $kind) {
                $preset = require resource_path("certificate-templates/malabar/{$kind}.php");
                $template = CertificateTemplate::firstOrCreate([
                    'tenant_id' => $tenant->id,
                    'event_type' => 'fest',
                    'event_id' => $event?->id,
                    'item_id' => null,
                    'certificate_type' => $preset['certificate_type'],
                    'title' => $preset['title'],
                ], $preset + ['background_path' => "{$base}.png"]);
                $this->command?->info("Malabar {$kind} template #{$template->id} ready for {$tenant->name}.");
            }
        };

        if (tenant('id')) {
            $seed();
        } else {
            $tenant->run($seed);
        }
    }
}
