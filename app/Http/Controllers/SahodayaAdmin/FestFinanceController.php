<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\FestPageActivity;
use App\Models\FestEvent;
use App\Models\FestEventInvoice;
use App\Models\Tenant;
use App\Services\Events\FestInvoiceService;
use App\Services\Audit\PlatformAuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class FestFinanceController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $invoices = FestEventInvoice::where('event_id', $event->id)
            ->with('school')
            ->orderBy('invoice_number')
            ->get()
            ->map(fn (FestEventInvoice $inv) => [
                'id'                       => $inv->id,
                'invoice_number'           => $inv->invoice_number,
                'school'                   => $inv->school?->name ?? $inv->school_id,
                'school_id'                => $inv->school_id,
                'school_registration_fee'  => $inv->school_registration_fee,
                'participation_fee'        => $inv->participation_fee,
                'participation_item_count' => $inv->participation_item_count,
                'total_amount'             => $inv->total_amount,
                'status'                   => $inv->status,
                'issued_at'                => $inv->issued_at?->toDateTimeString(),
            ]);

        return $this->inertia('Sahodaya/Events/Finance/Index', $this->withEventActivity($event, FestPageActivity::FINANCE, [
            'event'    => $event,
            'invoices' => $invoices,
            'summary'  => [
                'count'  => $invoices->count(),
                'total'  => $invoices->sum('total_amount'),
            ],
        ]));
    }

    public function issueAll(Request $request, string $tenantId, FestEvent $event, FestInvoiceService $service, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $issued = $service->issueAll($event, $request->user()->id);

        $audit->festEvent($event, FestPageActivity::FINANCE, 'fest.finance.invoices_issued', count($issued).' invoice(s) issued', [
            'count' => count($issued),
        ]);

        return back()->with('success', count($issued).' invoice(s) issued.');
    }

    public function issueSchool(Request $request, string $tenantId, FestEvent $event, string $schoolId, FestInvoiceService $service, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $school = Tenant::where('parent_id', $this->sahodaya->id)->findOrFail($schoolId);
        $service->issueForSchool($event, $school, $request->user()->id);

        $audit->festEvent($event, FestPageActivity::FINANCE, 'fest.finance.invoice_issued', 'Invoice issued for '.$school->name, [
            'school_id' => $schoolId,
        ]);

        return back()->with('success', 'Invoice issued for '.$school->name.'.');
    }

    public function pdf(Request $request, string $tenantId, FestEvent $event, FestEventInvoice $invoice)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($invoice->event_id !== $event->id, 404);

        return $this->renderInvoicePdf($request, $event, $invoice, 'fest.finance.invoice');
    }

    public function pdfDetailed(Request $request, string $tenantId, FestEvent $event, FestEventInvoice $invoice)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($invoice->event_id !== $event->id, 404);

        return $this->renderInvoicePdf($request, $event, $invoice, 'fest.finance.invoice-detailed', 'demand-');
    }

    private function renderInvoicePdf(Request $request, FestEvent $event, FestEventInvoice $invoice, string $view, string $prefix = '')
    {
        $invoice->load('school');
        $invoiceService = app(FestInvoiceService::class);
        $invoice = $invoiceService->issueForSchool($event, $invoice->school);

        $pdf = Pdf::loadView($view, $invoiceService->invoiceViewData($event, $invoice, $this->sahodaya));

        if ($request->boolean('preview')) {
            return $pdf->stream($prefix.$invoice->invoice_number.'.pdf');
        }

        return $pdf->download($prefix.$invoice->invoice_number.'.pdf');
    }
}
