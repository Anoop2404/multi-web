<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\Tenant;
use App\Services\Events\FestRegistrationFeeService;

class FestEventFeesController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $feeService = app(FestRegistrationFeeService::class);

        $registrations = FestRegistration::where('event_id', $event->id)
            ->with(['item', 'feeReceipt', 'participants'])
            ->orderBy('school_id')
            ->get();

        $schools = Tenant::whereIn('id', $registrations->pluck('school_id')->unique())
            ->pluck('name', 'id');

        $rows = $registrations->map(function (FestRegistration $r) use ($event, $feeService, $schools) {
            return [
                'id'         => $r->id,
                'school'     => $schools[$r->school_id] ?? $r->school_id,
                'item'       => $r->item?->title,
                'status'     => $r->status,
                'due'        => $feeService->amountDue($event, $r),
                'fee_receipt'=> $r->feeReceipt,
            ];
        });

        $summary = [
            'total_due'  => $rows->sum('due'),
            'total_paid' => $rows->sum(fn ($r) => $r['fee_receipt']?->status === 'approved' ? (float) $r['fee_receipt']->amount : 0),
            'pending'    => $rows->filter(fn ($r) => $r['due'] > 0 && ! $r['fee_receipt'])->count(),
            'awaiting'   => $rows->filter(fn ($r) => $r['fee_receipt']?->status === 'uploaded')->count(),
        ];

        return $this->inertia('Sahodaya/Events/Fees', [
            'event' => $event,
            'rows'  => $rows->values(),
            'summary' => $summary,
        ]);
    }
}
