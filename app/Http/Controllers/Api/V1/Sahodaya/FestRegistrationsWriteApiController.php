<?php

namespace App\Http\Controllers\Api\V1\Sahodaya;

use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Services\Events\EventLifecycleGate;
use App\Services\Events\FestRegistrationApprovalService;
use App\Services\Events\FestRegistrationBulkService;
use App\Services\Events\FestSchoolEventFeeService;
use Illuminate\Http\Request;

class FestRegistrationsWriteApiController extends SahodayaApiController
{
    public function approve(Request $request, FestEvent $event, FestRegistration $registration)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->event_id !== $event->id, 403);

        EventLifecycleGate::allowRegistrationReview($event, $request->boolean('override_lifecycle'));

        $feeService = app(FestSchoolEventFeeService::class);
        if ($feeService->feeRequired($event)) {
            abort_unless(
                $feeService->isPaid($event, $registration->school_id),
                422,
                'School event fee must be approved before registration approval.'
            );
        }

        app(FestRegistrationApprovalService::class)->approve($registration->load(['participants', 'item', 'event']));

        return response()->json(['data' => ['status' => 'approved']]);
    }

    public function reject(Request $request, FestEvent $event, FestRegistration $registration)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->event_id !== $event->id, 403);

        EventLifecycleGate::allowRegistrationReview($event, $request->boolean('override_lifecycle'));

        $registration->update(['status' => 'rejected']);
        app(FestSchoolEventFeeService::class)->recalculate($event, $registration->school_id);

        return response()->json(['data' => ['status' => 'rejected']]);
    }

    public function bulkApprove(Request $request, FestEvent $event, FestRegistrationBulkService $bulk)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'registration_ids'   => 'nullable|array',
            'school_id'          => 'nullable|exists:tenants,id',
            'override_lifecycle' => 'nullable|boolean',
        ]);

        $result = $bulk->approveMany(
            $event,
            $data['registration_ids'] ?? [],
            $data['school_id'] ?? null,
            (bool) ($data['override_lifecycle'] ?? false),
        );

        return response()->json(['data' => $result]);
    }
}
