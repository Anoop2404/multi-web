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
        $tenantId = tenant('id') ?? env('SAHODAYA_UUID');
        $tenant = Tenant::where('type', 'sahodaya')
            ->when($tenantId, fn ($q) => $q->where('id', $tenantId), fn ($q) => $q->where('subdomain', 'kannur'))
            ->first();

        if (! $tenant) {
            throw new \RuntimeException("Sahodaya tenant was not found for ID [{$tenantId}]. Create the account or pass the intended tenant ID.");
        }

        $seed = function (Tenant $tenant) {
            $event = FestEvent::where('tenant_id', $tenant->id)->whereNull('parent_event_id')
                ->whereRaw('LOWER(title) LIKE ?', ['%kalots%'])->where('title', 'like', '%2026%')->first();
            $base = "tenants/{$tenant->id}/certificate-backgrounds/kannur-kalotsav-2026-27";
            foreach (['pdf', 'png'] as $extension) {
                if (! Storage::disk('shared')->put("{$base}.{$extension}", file_get_contents(resource_path("certificate-templates/kannur/kalotsav-2026-27.{$extension}")))) {
                    throw new \RuntimeException('Failed to store the Kannur certificate artwork.');
                }
            }
            $preset = require resource_path('certificate-templates/kannur/merit.php');

            // 1. Merit (Winner) Template
            $meritTemplate = CertificateTemplate::firstOrCreate([
                'tenant_id' => $tenant->id, 'event_type' => $preset['event_type'],
                'event_id' => $event?->id, 'item_id' => null,
                'certificate_type' => 'winner', 'title' => $preset['title'],
            ], $preset + ['background_path' => "{$base}.png", 'template_file_path' => "{$base}.pdf"]);
            $this->command?->info("Kannur merit template #{$meritTemplate->id} ready for {$tenant->name}.");

            // 2. Participation Template
            $participationLayout = $preset['layout_json'] ?? [];
            // Cover "Merit" and the red flourish underneath cleanly with the certificate's cream background
            $participationLayout['custom_fields'] = [[
                'text' => '<div style="background:#f6f5f1;color:#1a365d;font-weight:bold;letter-spacing:1px;padding:6px 14px 10px;border-radius:4px;display:inline-block;box-shadow:0 0 1px #f6f5f1;">Participation</div>',
                'top' => 24.2,
                'left' => 56.2,
                'width' => 19,
                'font_size' => 28,
                'font_family' => 'Times New Roman',
                'align' => 'left',
            ]];
            $participationBody = '<div style="color:#222;line-height:1.4;">This is to certify that {salutation} {recipient_name_upper} of {school_name_upper} has participated in {item_title}, {category_name}, at the KANNUR SAHODAYA DISTRICT KALOTSAV 2026-27 held at {venue} on {event_dates}.</div>';
            $participationTemplate = CertificateTemplate::updateOrCreate([
                'tenant_id' => $tenant->id, 'event_type' => $preset['event_type'],
                'event_id' => $event?->id, 'item_id' => null,
                'certificate_type' => 'participation',
            ], [
                'event_type' => $preset['event_type'],
                'certificate_type' => 'participation',
                'title' => 'Kannur Sahodaya District Kalotsav 2026-27 — Certificate of Participation',
                'body' => $participationBody,
                'dynamic_fields_json' => [],
                'signatories' => [],
                'layout_json' => $participationLayout,
                'is_active' => true,
                'background_path' => "{$base}.png",
                'template_file_path' => "{$base}.pdf",
            ]);
            $this->command?->info("Kannur participation template #{$participationTemplate->id} ready for {$tenant->name}.");
        };

        if (tenant('id')) {
            $seed($tenant);
        } else {
            $tenant->run(fn () => $seed($tenant));
        }
    }
}
