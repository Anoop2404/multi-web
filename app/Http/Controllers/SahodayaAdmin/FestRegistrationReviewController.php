<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\Tenant;
use App\Services\Events\EventContext;
use App\Services\Events\FestEventNotifier;
use App\Services\Events\FestRegistrationFeeService;
use Illuminate\Http\Request;

class FestRegistrationReviewController extends SahodayaAdminController
{
    public function index(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $feeService = app(FestRegistrationFeeService::class);

        $registrations = FestRegistration::where('event_id', $event->id)
            ->with(['item', 'participants.student', 'participants.teacher', 'participants.group', 'feeReceipt'])
            ->latest()
            ->get()
            ->map(function (FestRegistration $r) use ($event, $feeService) {
                $r->setAttribute('fee_due', $feeService->amountDue($event, $r));
                $r->setAttribute('fee_required', $feeService->feeRequired($event));

                return $r;
            });

        $schools = Tenant::where('parent_id', $this->sahodaya->id)
            ->pluck('name', 'id');

        return $this->inertia('Sahodaya/Events/Registrations', [
            'event'         => $event,
            'registrations' => $registrations,
            'schools'       => $schools,
        ]);
    }

    public function approve(Request $request, string $tenantId, FestEvent $event, FestRegistration $registration)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->event_id !== $event->id, 403);

        $feeService = app(FestRegistrationFeeService::class);
        if ($feeService->feeRequired($event) && ! $registration->fee_receipt_id) {
            return back()->with('error', 'School must upload payment proof before approval.');
        }

        if ($registration->feeReceipt && $registration->feeReceipt->status === 'uploaded') {
            $registration->feeReceipt->update([
                'status'      => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
            app(\App\Services\Events\FestFeeLedgerService::class)
                ->postApprovedReceipt($registration->feeReceipt->fresh());
        }

        $registration->update(['status' => 'approved']);

        $ctx = EventContext::for($event);
        foreach ($registration->participants as $participant) {
            if (! $participant->chest_no && $registration->item_id) {
                $participant->update([
                    'chest_no' => $ctx->nextChestNumber($registration->item),
                ]);
            }
        }

        app(FestEventNotifier::class)->registrationApproved($registration);

        return back()->with('success', 'Registration approved.');
    }

    public function reject(Request $request, string $tenantId, FestEvent $event, FestRegistration $registration)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->event_id !== $event->id, 403);

        $registration->update(['status' => 'rejected']);
        app(FestEventNotifier::class)->registrationRejected($registration);

        return back()->with('success', 'Registration rejected.');
    }
}
