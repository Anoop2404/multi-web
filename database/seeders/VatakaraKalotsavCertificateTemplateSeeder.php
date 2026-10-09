<?php

namespace Database\Seeders;

use App\Models\CertificateTemplate;
use App\Models\FestEvent;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class VatakaraKalotsavCertificateTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = tenant('id') ?? env('SAHODAYA_UUID');
        $tenant = Tenant::where('type', 'sahodaya')
            ->when($tenantId, fn ($query) => $query->where('id', $tenantId),
                fn ($query) => $query->whereIn('subdomain', ['vatakara', 'vadakara']))
            ->firstOrFail();

        $seed = function () use ($tenant): void {
            $eventId = env('FEST_EVENT_ID');
            $event = $eventId
                ? FestEvent::where('tenant_id', $tenant->id)->findOrFail($eventId)
                : null;
            $base = "tenants/{$tenant->id}/certificate-backgrounds/vatakara-kalotsav-kids-fest-2026";
            foreach (['pdf', 'png'] as $extension) {
                $source = resource_path("certificate-templates/vatakara/kalotsav-kids-fest-2026.{$extension}");
                if (! Storage::disk('shared')->put("{$base}.{$extension}", file_get_contents($source))) {
                    throw new \RuntimeException('Failed to store Vatakara certificate artwork.');
                }
            }
            $preset = require resource_path('certificate-templates/vatakara/merit.php');
            $template = CertificateTemplate::firstOrCreate([
                'tenant_id' => $tenant->id,
                'event_type' => 'fest',
                'event_id' => $event?->id,
                'item_id' => null,
                'certificate_type' => 'winner',
                'title' => $preset['title'],
            ], $preset + [
                'background_path' => "{$base}.png",
                'template_file_path' => "{$base}.pdf",
            ]);
            $this->command?->info("Vatakara merit template #{$template->id} ready for {$tenant->name}.");
        };

        if (tenant('id')) {
            $seed();
        } else {
            $tenant->run($seed);
        }
    }
}
