<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestSchoolEventFee;
use App\Support\ProgramRouteMap;
use Illuminate\Http\Request;

class FestPaymentsController extends SahodayaAdminController
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');
        $program = $request->query('program');

        $eventIds = FestEvent::where('tenant_id', $this->sahodaya->id)
            ->when($program, fn ($q) => $q->where('event_type', ProgramRouteMap::eventTypeFromSlug($program)))
            ->pluck('id');

        $base = FestSchoolEventFee::query()
            ->whereIn('event_id', $eventIds)
            ->forAmountAggregation()
            ->with([
                'event:id,title,event_type,level_round', 'school:id,name', 'feeReceipt', 'registrationBatch:id,name,code',
                // Installments (see FestSchoolEventFeeService::claimableBalance()) mean more
                // than one receipt can be 'uploaded' and awaiting review at once — surfaced
                // below as receipts_history/pending_total so the queue doesn't just show the
                // latest one and silently hide the rest.
                'receipts' => fn ($q) => $q->latest('id')->with(['reviewedBy:id,name', 'attachments']),
            ]);

        $counts = [
            'pending'  => (clone $base)->where('status', 'proof_uploaded')
                ->whereHas('feeReceipt', fn ($q) => $q->where('status', 'uploaded'))->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'all'      => (clone $base)->count(),
        ];

        $query = clone $base;
        if ($status === 'pending') {
            $query->where('status', 'proof_uploaded')
                ->whereHas('feeReceipt', fn ($q) => $q->where('status', 'uploaded'));
        } elseif ($status === 'approved') {
            $query->where('status', 'approved');
        }

        $fees = $query->orderByDesc('updated_at')->paginate(20)->withQueryString();
        $fees->getCollection()->transform(fn (FestSchoolEventFee $sf) => $this->mapFeeRow($sf));

        return $this->inertia('Sahodaya/Fest/Payments/Index', [
            'fees'         => $fees,
            'activeStatus' => $status,
            'statusCounts' => $counts,
            'programFilter'=> $program,
            'programOptions' => collect(ProgramRouteMap::festProgramSlugs())->map(fn ($slug) => [
                'slug'  => $slug,
                'label' => ProgramRouteMap::labelForSlug($slug),
            ])->values(),
        ]);
    }

    public function approve(Request $request, string $tenantId, FestSchoolEventFee $schoolEventFee)
    {
        abort_if($schoolEventFee->event?->tenant_id !== $this->sahodaya->id, 403);

        return app(FestSchoolEventFeeController::class)
            ->approve($request, $tenantId, $schoolEventFee->event, $schoolEventFee, app(\App\Services\Audit\PlatformAuditLogger::class));
    }

    public function reject(Request $request, string $tenantId, FestSchoolEventFee $schoolEventFee)
    {
        abort_if($schoolEventFee->event?->tenant_id !== $this->sahodaya->id, 403);

        return app(FestSchoolEventFeeController::class)
            ->reject($request, $tenantId, $schoolEventFee->event, $schoolEventFee, app(\App\Services\Audit\PlatformAuditLogger::class));
    }

    public function proof(string $tenantId, FestSchoolEventFee $schoolEventFee)
    {
        abort_if($schoolEventFee->event?->tenant_id !== $this->sahodaya->id, 403);

        return app(FestSchoolEventFeeController::class)
            ->proof($tenantId, $schoolEventFee->event, $schoolEventFee);
    }

    /** @return array<string, mixed> */
    private function mapFeeRow(FestSchoolEventFee $sf): array
    {
        $event = $sf->event;
        $programSlug = $event ? ProgramRouteMap::slugFromEventType($event->event_type) : null;

        return [
            'id'             => $sf->id,
            'event_id'       => $sf->event_id,
            'event_title'    => $event?->title,
            'event_type'     => $event?->event_type,
            'program_label'  => $programSlug ? ProgramRouteMap::labelForSlug($programSlug) : 'Event',
            'level_round'    => $event?->level_round,
            'billing_level'  => $sf->registrationBatch?->name,
            'billing_code'   => $sf->registrationBatch?->code,
            'school_id'      => $sf->school_id,
            'school_name'    => $sf->school?->name,
            'total_due'      => (float) $sf->total_due,
            'status'         => $sf->status,
            'updated_at'     => $sf->updated_at?->format('j M Y, g:i A'),
            'fee_receipt'    => $sf->feeReceipt ? [
                'id'              => $sf->feeReceipt->id,
                'status'          => $sf->feeReceipt->status,
                'amount'          => (float) $sf->feeReceipt->amount,
                'receipt_number'  => $sf->feeReceipt->receipt_number,
                'payment_date'    => $sf->feeReceipt->payment_date?->format('Y-m-d'),
                'transaction_ref' => $sf->feeReceipt->transaction_ref,
                'proof_url'       => $sf->feeReceipt->file_path
                    ? "/sahodaya-admin/{$this->sahodaya->id}/fest/payments/{$sf->id}/proof"
                    : null,
            ] : null,
            // How many receipts are currently 'uploaded' (pending review) and their combined
            // amount — a school can submit several installments before any are reviewed
            // (claimableBalance()), so this can be several receipts even though only one is
            // shown by fee_receipt above. 0/1 for the overwhelming majority of rows.
            'pending_count'  => $sf->receipts->where('status', 'uploaded')->count(),
            'pending_total'  => (float) $sf->receipts->where('status', 'uploaded')->sum('amount'),
            'receipts_history' => $sf->receipts->map(fn ($r) => [
                'id'               => $r->id,
                'status'           => $r->status,
                'amount'           => (float) $r->amount,
                'receipt_number'   => $r->receipt_number,
                'transaction_ref'  => $r->transaction_ref,
                'bank_name'        => $r->bank_name,
                'payment_date'     => $r->payment_date?->format('Y-m-d'),
                'uploaded_at'      => $r->created_at?->format('j M Y, g:i A'),
                'reviewed_at'      => $r->reviewed_at?->format('j M Y, g:i A'),
                'reviewed_by'      => $r->reviewedBy?->name,
                'rejection_reason' => $r->rejection_reason,
                'proof_url'        => $r->file_path
                    ? "/sahodaya-admin/{$this->sahodaya->id}/events/{$sf->event_id}/school-fees/{$sf->id}/proofs/{$r->id}"
                    : null,
                // Extra evidence images for this same payment (e.g. a bank statement page
                // alongside a UTR screenshot) — attachPayment() already saves these via
                // FeeReceiptAttachmentService::attachExtra(), they just weren't exposed here,
                // so admin only ever saw the first of several files a school submitted.
                'attachments'      => $r->attachments->map(fn ($a) => [
                    'id'  => $a->id,
                    'url' => "/sahodaya-admin/{$this->sahodaya->id}/finance/payments/attachments/{$a->id}",
                ])->values()->all(),
            ])->values()->all(),
            'event_fees_url' => $event
                ? "/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}/fees"
                : null,
        ];
    }
}
