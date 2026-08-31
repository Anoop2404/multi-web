<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\Tenant;
use App\Support\ExcelExport;
use App\Support\FestClassGroupScheme;
use App\Support\FestItemCategoryLabel;
use App\Support\PdfGenerator;
use App\Support\TenantBranding;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FestSchoolReportExportService
{
    private bool $preview = false;

    /**
     * Sahodaya branding (org name + logo data URI) for PDF report headers.
     *
     * @return array{orgName: string, logoSrc: ?string}
     */
    private function renderPdf(string $view, array $data, string $filename, bool $landscape = false)
    {
        return PdfGenerator::download(view($view, $data)->render(), $filename, $this->preview, $landscape);
    }

    private function brandingData(FestEvent $event): array
    {
        $sahodaya = Tenant::find($event->tenant_id);

        return [
            'orgName' => $sahodaya?->name ?? 'Sahodaya',
            'logoSrc' => $sahodaya ? TenantBranding::logoEmbedSrc($sahodaya) : null,
        ];
    }

    public function registrationRegisterPdf(
        FestEvent $event,
        Tenant $school,
        FestRegistrationRegisterService $register,
    ): Response {
        $data = $register->build($event, $school->id);
        $slug = str($event->title)->slug()->limit(40);

        // Chest numbers are Sahodaya-admin-only information — schools don't see them until
        // assigned on fest day. build() is shared with Sahodaya-admin (which does need this
        // field), so the strip happens here rather than in that shared service.
        $rows = collect($data['rows'])->map(fn (array $row) => \Illuminate\Support\Arr::except($row, ['chest_no']));

        return $this->renderPdf('fest.reports.school-registration-register', [
            'event'   => $event,
            'school'  => $school,
            'rows'    => $rows,
            'summary' => $data['school_summaries'][0] ?? null,
            ...$this->brandingData($event),
        ], "{$slug}-registration-register.pdf", true);
    }

    public function headWisePdf(
        FestEvent $event,
        Tenant $school,
        Request $request,
    ): Response {
        $headId = $request->integer('head_id') ?: null;
        $analytics = new FestSchoolReportAnalyticsService($event, $school->id);
        $slug = str($event->title)->slug()->limit(40);

        return $this->renderPdf('fest.reports.head-wise-school', [
            'event'   => $event,
            'school'  => $school,
            'summary' => $analytics->headRegistrationSummary(),
            'rows'    => $analytics->headWiseParticipantRows($headId),
            ...$this->brandingData($event),
        ], "{$slug}-head-wise-participants.pdf", true);
    }

    /** @param list<array<string, mixed>> $rows */
    public function itemWiseParticipantsPdf(
        FestEvent $event,
        Tenant $school,
        \App\Models\FestEventItem $item,
        array $rows,
    ): Response {
        $slug = str($event->title)->slug()->limit(40);
        $itemSlug = str($item->title)->slug()->limit(30);
        $categoryLabel = FestItemCategoryLabel::resolve($item, FestClassGroupScheme::labels(null, $event->rootEvent()));

        return $this->renderPdf('fest.reports.item-wise-school', [
            'event'         => $event,
            'school'        => $school,
            'item'          => $item,
            'categoryLabel' => $categoryLabel,
            'rows'          => $rows,
            ...$this->brandingData($event),
        ])->download("{$slug}-{$itemSlug}-participants.pdf");
    }

    /** @param list<array<string, mixed>> $rows */
    public function itemParticipantsExcel(FestEvent $event, int $itemId, array $rows): StreamedResponse
    {
        $item = \App\Models\FestEventItem::whereIn('event_id', $event->reportableEventIds())->find($itemId);
        $title = $item?->title ?? 'item';

        $data = collect($rows)->map(fn (array $r) => [
            $r['name'] ?? '—',
            $r['reg_no'] ?? '—',
            $r['class'] ?? '—',
            $r['fest_id'] ?? '—',
            $r['item_reg'] ?? '—',
            ucfirst((string) ($r['status'] ?? '—')),
        ]);

        return ExcelExport::download(
            str($event->title)->slug()->limit(30).'-'.str($title)->slug()->limit(24).'-participants',
            ['Participant', 'Reg no', 'Class', 'Fest ID', 'Item reg', 'Status'],
            $data,
        );
    }

    /**
     * School-only twin of FestEventReportAnalyticsService::exportNumberingRegister() —
     * same column layout minus the Chest column, since chest numbers are Sahodaya-admin-
     * only information (see FestSchoolReportAnalyticsService::numberingRegisterRows()).
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function numberingRegisterExcel(FestEvent $event, array $rows): StreamedResponse
    {
        $data = collect($rows)->map(fn (array $r) => [
            $r['head_name'] ?? '—',
            $r['item'] ?? '',
            $r['name'] ?? '',
            $r['reg_no'] ?? '',
            $r['reg_status'] ?? '',
            $r['role'] ?? '',
            $r['fest_id'] ?? '',
            $r['item_reg'] ?? '',
        ]);

        return ExcelExport::download(
            str($event->title)->slug()->limit(40).'-numbering-register',
            ['Head', 'Item', 'Participant', 'Reg no', 'Reg status', 'Role', 'Fest ID', 'Item reg'],
            $data,
        );
    }

    public function disciplinePdf(FestEvent $event, Tenant $school, array $rows): Response
    {
        $slug = str($event->title)->slug()->limit(40);

        return $this->renderPdf('fest.reports.discipline-school', [
            'event'  => $event,
            'school' => $school,
            'rows'   => $rows,
            ...$this->brandingData($event),
        ])->download("{$slug}-discipline-participation.pdf");
    }

    public function participationPdf(FestEvent $event, Tenant $school, array $used, array $limits): Response
    {
        $slug = str($event->title)->slug()->limit(40);

        return $this->renderPdf('fest.reports.participation-school', [
            'event'  => $event,
            'school' => $school,
            'used'   => $used,
            'limits' => $limits,
            ...$this->brandingData($event),
        ])->download("{$slug}-participation-limits.pdf");
    }

    public function markEntryStatusPdf(FestEvent $event, Tenant $school, array $rows, array $summary): Response
    {
        $slug = str($event->title)->slug()->limit(40);

        return $this->renderPdf('fest.reports.mark-entry-school', [
            'event'   => $event,
            'school'  => $school,
            'rows'    => $rows,
            'summary' => $summary,
            ...$this->brandingData($event),
        ], "{$slug}-mark-entry-status.pdf", true);
    }

    public function viaFestReport(FestEvent $event, string $exportType, Request $request): Response|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        return (new FestReportService($event))->export($exportType, $request);
    }

    /** @param list<array<string, mixed>> $headSummary */
    public function itemCountsPdf(FestEvent $event, Tenant $school, array $headSummary, array $rows, array $totals): Response
    {
        $slug = str($event->title)->slug()->limit(40);

        return $this->renderPdf('fest.reports.school-item-counts', [
            'event'       => $event,
            'school'      => $school,
            'headSummary' => $headSummary,
            'rows'        => $rows,
            'totals'      => $totals,
            ...$this->brandingData($event),
        ], "{$slug}-item-registration-counts.pdf", true);
    }

    /** @param list<array<string, mixed>> $rows */
    public function itemCountsExcel(FestEvent $event, array $rows): StreamedResponse
    {
        $data = collect($rows)->map(fn ($r) => [
            $r['head_name'] ?? '—',
            $r['title'],
            $r['item_code'] ?? '',
            $r['age_group'] ?? $r['class_group'] ?? '',
            $r['approved'],
            $r['pending'],
            $r['registration_count'],
            $r['participant_count'],
            $r['item_reg_assigned'],
            $r['max_per_school'] ?? '',
            $r['fee_per_item'] ?? '',
            $r['line_fee'] ?? '',
        ]);

        return ExcelExport::download(
            str($event->title)->slug()->limit(40).'-item-counts',
            ['Head', 'Item', 'Code', 'Age/Class', 'Approved', 'Pending', 'Total regs', 'Participants', 'Item IDs', 'Max/school', 'Fee/item', 'Line fee'],
            $data,
        );
    }
}
