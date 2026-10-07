<?php

namespace Database\Seeders;

use App\Models\CertificateTemplate;
use App\Models\FestEvent;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class KannurKalotsavCertificateTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = env('SAHODAYA_UUID');
        $tenant = Tenant::where('type', 'sahodaya')
            ->when($tenantId, fn ($q) => $q->where('id', $tenantId), fn ($q) => $q->where('subdomain', 'kannur'))
            ->first();

        if (! $tenant) {
            throw new \RuntimeException('Kannur Sahodaya was not found. Create the local account or set SAHODAYA_UUID to the intended Sahodaya UUID.');
        }

        $tenant->run(function () use ($tenant) {
            $event = FestEvent::where('tenant_id', $tenant->id)->whereNull('parent_event_id')
                ->whereRaw('LOWER(title) LIKE ?', ['%kalots%'])->where('title', 'like', '%2026%')->first();
            $base = "tenants/{$tenant->id}/certificate-backgrounds/kannur-kalotsav-2026-27";
            foreach (['pdf', 'png'] as $extension) {
                if (! Storage::disk('shared')->put("{$base}.{$extension}", file_get_contents(resource_path("certificate-templates/kannur/kalotsav-2026-27.{$extension}")))) {
                    throw new \RuntimeException('Failed to store the Kannur certificate artwork.');
                }
            }
            $preset = require resource_path('certificate-templates/kannur/merit.php');
            $template = CertificateTemplate::firstOrCreate([
                'tenant_id' => $tenant->id, 'event_type' => $preset['event_type'],
                'event_id' => $event?->id, 'item_id' => null,
                'certificate_type' => $preset['certificate_type'], 'title' => $preset['title'],
            ], $preset + ['background_path' => "{$base}.png", 'template_file_path' => "{$base}.pdf"]);
            $this->command?->info("Kannur merit template #{$template->id} ready for {$tenant->name}.");
        });
    }
}
