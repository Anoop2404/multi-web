<?php

namespace Database\Seeders;

use App\Models\CertificateTemplate;
use App\Models\FestEvent;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class KochiMetroKalotsavCertificateTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = tenant('id') ?? env('SAHODAYA_UUID');
        $tenant = Tenant::where('type', 'sahodaya')
            ->when($tenantId, fn ($q) => $q->where('id', $tenantId), fn ($q) => $q->where('subdomain', 'kochimetro'))
            ->firstOrFail();

        $seed = function (Tenant $tenant) {
            $event = $tenant->subdomain === 'kochimetro'
                ? FestEvent::where('tenant_id', $tenant->id)
                    ->whereNull('parent_event_id')->where('title', 'Kochi Metro Kalotsav 2026-27')->firstOrFail()
                : null;
            $path = "tenants/{$tenant->id}/certificate-backgrounds/kochi-metro-kalotsav-2026-27.jpg";
            Storage::disk('shared')->put($path, file_get_contents(resource_path('certificate-templates/kochi-metro/kalotsav-2026-27.jpg')));

            DB::transaction(function () use ($tenant, $event, $path) {
                foreach (['winner' => 'Merit', 'participation' => 'Participation'] as $type => $label) {
                    $layout = CertificateTemplate::defaultBackgroundLayout();
                    $layout = array_replace($layout, [
                        'show_photo' => true,
                        'show_recipient_name' => false,
                        'show_certificate_date' => false,
                        'show_logo_overlay' => false,
                        'show_qr' => false,
                        'photo' => ['top' => 25.8, 'left' => 14.6, 'size' => 187],
                        'body' => ['top' => 51.2, 'left' => 8.3, 'width' => 61, 'bottom' => 75,
                            'font_size' => 20, 'font_family' => 'Arial', 'font_weight' => 'normal', 'align' => 'left'],
                    ]);
                    if ($type === 'participation') {
                        // Cover only the printed subtitle; preserve the supplied artwork.
                        $layout['custom_fields'] = [[
                            'text' => '<div style="background:white;color:#a5aa8c;line-height:1.15;padding:2px 0 5px;letter-spacing:2px;">OF PARTICIPATION</div>',
                            'top' => 39, 'left' => 25, 'width' => 44.3, 'font_size' => 29,
                            'font_family' => 'Times New Roman', 'align' => 'right',
                        ]];
                    }
                    $achievement = $type === 'winner' ? 'has {achievement_line}' : 'has participated';
                    $body = '<div style="color:#000;line-height:1.25;">This is to certify that {salutation} {recipient_name_upper}<br>'
                        .'of {school_name_upper} '.$achievement.' in {item_title} {category_name} in the '
                        .'KOCHI METRO SAHODAYA DISTRICT KALOTSAV 2026-27 held at '
                        ."St. Mary’s Public School, Thamarachal on {event_dates}.</div>";
                    $template = CertificateTemplate::firstOrCreate([
                        'tenant_id' => $tenant->id, 'event_type' => 'fest', 'event_id' => $event?->id,
                        'item_id' => null, 'certificate_type' => $type,
                        'title' => "Kochi Metro Sahodaya Kalotsav 2026-27 — Certificate of {$label}",
                    ], [
                        'body' => $body, 'background_path' => $path, 'layout_json' => $layout,
                        'dynamic_fields_json' => [], 'signatories' => [], 'is_active' => true,
                    ]);
                    $this->command?->info("{$label} template #{$template->id} ready for {$tenant->name}.");
                }
            });
        };

        if (tenant('id')) {
            $seed($tenant);
        } else {
            $tenant->run(fn () => $seed($tenant));
        }
    }
}
