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
            ->with(['event:id,title,event_type,level_round', 'school:id,name', 'feeReceipt']);

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
            'event_fees_url' => $event
                ? "/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}/fees"
                : null,
        ];
    }
}
