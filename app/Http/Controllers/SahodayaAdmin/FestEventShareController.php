<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
// Generic branded-QR-poster generation (png/svg/pdf from a url + branding array) —
// nothing here is training-specific despite the class's home package, so it's reused
// as-is rather than duplicated.
use App\Services\Training\TrainingQrService;
use App\Support\TenantBranding;
use App\Support\TenantDomainSync;
use Barryvdh\DomPDF\Facade\Pdf;

class FestEventShareController extends SahodayaAdminController
{
    public function qrImage(string $tenantId, FestEvent $event, TrainingQrService $qr)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $url = $this->publicEventUrl($event);
        $branding = $this->branding($event, $url);

        return response($qr->brandedPng($url, $branding), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$this->slug($event).'.png"',
        ]);
    }

    public function qrPdf(string $tenantId, FestEvent $event, TrainingQrService $qr)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $url = $this->publicEventUrl($event);
        $branding = $this->branding($event, $url);

        $pdf = Pdf::loadView('public.fest.qr-poster', [
            'orgName' => $branding['org_name'],
            'orgTag' => $branding['org_tag'],
            'logoSrc' => $branding['logo_src'],
            'title' => $branding['program_title'],
            'label' => $branding['label'],
            'instruction' => $branding['instruction'],
            'venue' => $branding['venue'],
            'dates' => $branding['dates'],
            'url' => $branding['url'],
            'qrDataUri' => $qr->dataUri($url, 400),
        ])->setPaper('a4', 'portrait');

        return $pdf->download($this->slug($event).'.pdf');
    }

    private function publicEventUrl(FestEvent $event): string
    {
        $root = rtrim((string) (TenantDomainSync::publicUrl($this->sahodaya) ?? url('/')), '/');

        return "{$root}/fest/{$event->id}";
    }

    /** @return array{org_name: string, org_tag: string, logo_src: ?string, program_title: string, label: string, instruction: string, venue: ?string, dates: ?string, url: string} */
    private function branding(FestEvent $event, string $url): array
    {
        $dates = null;
        if ($event->event_start) {
            $dates = $event->event_start->format('d M Y');
            if ($event->event_end && ! $event->event_end->isSameDay($event->event_start)) {
                $dates .= ' – '.$event->event_end->format('d M Y');
            }
        }

        $venue = $event->resolvedVenueName();

        return [
            'org_name' => $this->sahodaya->name,
            'org_tag' => 'Public Event Portal',
            'logo_src' => TenantBranding::logoEmbedSrc($this->sahodaya),
            'program_title' => $event->title,
            'label' => 'Scan to Follow',
            'instruction' => 'Scan with your phone camera',
            'venue' => filled($venue) ? $venue : null,
            'dates' => $dates,
            'url' => $url,
        ];
    }

    private function slug(FestEvent $event): string
    {
        return str($event->title)->slug().'-qr';
    }
}
