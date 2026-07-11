<?php

namespace App\Services\Fees;

use App\Models\FeeReceipt;
use App\Models\FestSchoolEventFee;
use App\Models\McqSchoolFee;
use App\Models\MembershipPayment;
use App\Models\Tenant;
use App\Models\TrainingRegistration;
use App\Services\Membership\MembershipNotifier;
use App\Services\Membership\MembershipReceiptService;
use App\Support\ProgramRouteMap;

class ReceiptEmailResendService
{
    public function __construct(
        private FeeReceiptEmailTracker $tracker,
        private OfflineProgramFeeOrchestrator $orchestrator,
        private ProgramFeeReceiptService $receiptService,
        private MembershipReceiptService $membershipReceipts,
        private MembershipNotifier $notifier,
    ) {}

    public function resend(FeeReceipt $receipt): bool
    {
        abort_unless($receipt->status === 'approved', 422, 'Receipt must be approved.');

        $this->tracker->incrementResend($receipt);
        $this->tracker->markQueued($receipt);

        if ($receipt->feeable_type === (new MembershipPayment)->getMorphClass()) {
            return $this->resendMembership($receipt);
        }

        $schoolId = $this->receiptService->schoolIdForReceipt($receipt);
        $school = $schoolId ? Tenant::find($schoolId) : null;
        abort_if(! $school, 422, 'School not found for receipt.');

        if ($receipt->feeable_type === (new FestSchoolEventFee)->getMorphClass()) {
            $fee = FestSchoolEventFee::with('event')->find($receipt->feeable_id);
            $slug = ProgramRouteMap::slugFromEventType($fee?->event?->event_type) ?? 'kalotsav';
            $html = $this->receiptService->renderFestSchoolEventFee($fee);

            $ok = $this->orchestrator->notifyApproved(
                $school,
                $receipt,
                ProgramRouteMap::labelForSlug($slug).' fee',
                $fee?->event?->title ?? 'Fest',
                $html,
                "programs/{$slug}/registration",
            );
            if ($ok) {
                $this->tracker->markSent($receipt->fresh());
            }

            return $ok;
        }

        if ($receipt->feeable_type === (new McqSchoolFee)->getMorphClass()) {
            $fee = McqSchoolFee::with('exam')->find($receipt->feeable_id);
            $ok = $this->orchestrator->notifyApproved($school, $receipt, 'Talent Search exam fee', $fee?->exam?->title ?? 'Talent Search Exam');
            if ($ok) {
                $this->tracker->markSent($receipt->fresh());
            }

            return $ok;
        }

        if ($receipt->feeable_type === (new TrainingRegistration)->getMorphClass()) {
            $reg = TrainingRegistration::with('program')->find($receipt->feeable_id);
            $ok = $this->orchestrator->notifyApproved($school, $receipt, 'Training fee', $reg?->program?->title ?? 'Training Program');
            if ($ok) {
                $this->tracker->markSent($receipt->fresh());
            }

            return $ok;
        }

        if ($receipt->feeable_type === (new \App\Models\TrainingSchoolFee)->getMorphClass()) {
            $fee = \App\Models\TrainingSchoolFee::with('program')->find($receipt->feeable_id);
            $ok = $this->orchestrator->notifyApproved($school, $receipt, 'Training batch fee', $fee?->program?->title ?? 'Training Program');
            if ($ok) {
                $this->tracker->markSent($receipt->fresh());
            }

            return $ok;
        }

        return false;
    }

    private function resendMembership(FeeReceipt $receipt): bool
    {
        $payment = MembershipPayment::with(['school', 'registration'])->find($receipt->feeable_id);
        abort_if(! $payment?->school, 422, 'Membership payment not found.');

        $html = $this->membershipReceipts->readGeneratedReceipt($receipt);
        $this->notifier->registrationCompleted(
            $payment->school,
            $payment->academic_year,
            $payment->registration?->reg_no ?? '—',
            false,
            $html,
            $receipt->receipt_number,
        );
        $this->tracker->markSent($receipt->fresh());

        return true;
    }
}
