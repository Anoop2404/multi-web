<?php

namespace App\Services\School;

use App\Models\FestEvent;
use App\Models\McqExam;
use App\Models\McqSchoolFee;
use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\Tenant;
use App\Services\Events\FestRegistrationBatchFeeService;
use App\Services\Events\FestSchoolEventFeeService;
use App\Support\AcademicYear;

class SchoolDocumentDownloadGateService
{
    public function __construct(
        private FestSchoolEventFeeService $festFees,
        private FestRegistrationBatchFeeService $batchFees,
    ) {}

    /** Sahodaya annual membership fee verified for the current academic year. */
    public function membershipFeeCleared(Tenant $school): bool
    {
        if ($school->membership_status === 'approved') {
            return true;
        }

        $year = AcademicYear::forSchool($school);

        $registration = Registration::where('school_id', $school->id)
            ->where('academic_year', $year)
            ->first();

        if ($registration && in_array($registration->registration_status, ['completed', 'approved'], true)) {
            return true;
        }

        if ($registration && (float) ($registration->membership_fee_amount ?? 0) <= 0
            && in_array($registration->registration_status, ['payment_pending', 'payment_submitted', 'completed'], true)) {
            return true;
        }

        return MembershipPayment::where('school_id', $school->id)
            ->where('academic_year', $year)
            ->where('status', 'verified')
            ->exists();
    }

    /**
     * @param  ?int  $headId  When given and the event bills sports_composite fees per Event Head,
     *                        only that head's fee needs to be paid — a school can clear Athletics
     *                        while Chess is still pending. Omit (or pass null) to fall back to the
     *                        old "whole event fee" check for every other event/fee model.
     * @param  ?int  $phaseId  When given and the event bills Kalotsavam fees per named Phase, only
     *                         that phase's fee needs to be paid — phases are independently payable
     *                         (see FestSchoolEventFeeService::recalculateForPhase()), so a school
     *                         that has fully paid Phase 1 must not be blocked from Phase 1
     *                         downloads just because Phase 2 is still unpaid. Omit (or pass null)
     *                         to fall back to the whole-event check, e.g. for a bundle spanning
     *                         every phase.
     * @param  ?int  $batchId  When given and the event bills per registration level/batch
     *                         (workflow_mode = phased_regional_billing), only that level's fee
     *                         needs to be paid. Same reasoning as $phaseId, for the other
     *                         phased fee model: FestSchoolEventFeeService::isPaid() clears only
     *                         once EVERY level is paid, which wrongly blocked Level 1 ID cards
     *                         for a school whose Level 1 invoice is approved while Level 2 is
     *                         still outstanding.
     * @param  string  $documentType  'id_card' opts into the event's own
     *                                fee_settings.id_card_allowed_with_pending_fees escape hatch
     *                                below; every other caller (e.g. admit cards) passes the
     *                                default and always enforces the fee gate as before.
     */
    public function festEventFeeCleared(FestEvent $event, Tenant $school, ?int $headId = null, ?int $phaseId = null, ?int $batchId = null, string $documentType = 'default'): bool
    {
        if ($documentType === 'id_card' && (bool) ($event->fee_settings['id_card_allowed_with_pending_fees'] ?? false)) {
            return true;
        }

        if ($batchId !== null && $event->usesPhasedRegionalBilling()) {
            return $this->batchFees->isBatchPaid($event, $school->id, $batchId);
        }

        if ($headId !== null && $this->festFees->usesPerHeadBilling($event)) {
            return $this->festFees->isHeadPaid($event, $school->id, $headId);
        }

        if ($phaseId !== null && $this->festFees->usesPerPhaseBilling($event)) {
            return $this->festFees->isPhasePaid($event, $school->id, $phaseId);
        }

        return $this->festFees->isPaid($event, $school->id);
    }

    public function mcqExamFeeCleared(McqExam $exam, Tenant $school): bool
    {
        if (! $exam->hasFee()) {
            return true;
        }

        $fee = McqSchoolFee::where('exam_id', $exam->id)
            ->where('school_id', $school->id)
            ->first();

        if (! $fee) {
            return false;
        }

        if ((float) $fee->total_due <= 0) {
            return true;
        }

        return in_array($fee->status, ['approved', 'waived'], true);
    }

    public function assertMembershipFeeForDownloads(Tenant $school): void
    {
        if ($this->membershipFeeCleared($school)) {
            return;
        }

        abort(422, 'Sahodaya membership fee payment is pending. Pay and get it verified before downloading ID cards or hall tickets.');
    }

    public function assertFestEventFeeForDownloads(FestEvent $event, Tenant $school, ?int $headId = null, ?int $phaseId = null, ?int $batchId = null, string $documentType = 'default'): void
    {
        $this->assertMembershipFeeForDownloads($school);

        if ($this->festEventFeeCleared($event, $school, $headId, $phaseId, $batchId, $documentType)) {
            return;
        }

        $message = match (true) {
            $batchId !== null && $event->usesPhasedRegionalBilling() =>
                $this->batchLabel($event, $batchId).' fee payment is pending. Upload payment proof for this level and wait for verification before downloading ID cards or hall tickets.',
            $headId !== null && $this->festFees->usesPerHeadBilling($event) =>
                'Event Head fee payment is pending. Upload payment proof for this head and wait for verification before downloading ID cards or hall tickets.',
            $phaseId !== null && $this->festFees->usesPerPhaseBilling($event) =>
                'This phase\'s fee payment is pending. Upload payment proof for this phase and wait for verification before downloading ID cards or hall tickets.',
            default =>
                'Event fee payment is pending. Upload payment proof and wait for verification before downloading ID cards or hall tickets.',
        };

        abort(422, $message);
    }

    public function assertMcqExamFeeForDownloads(McqExam $exam, Tenant $school): void
    {
        $this->assertMembershipFeeForDownloads($school);

        if ($this->mcqExamFeeCleared($exam, $school)) {
            return;
        }

        abort(422, 'Talent Search exam fee payment is pending. Upload payment proof and wait for verification before downloading hall tickets or credentials.');
    }

    /**
     * @param  ?int  $headId  See festEventFeeCleared().
     * @param  ?int  $phaseId  See festEventFeeCleared().
     * @return array{blocked: bool, reason: ?string, membership_cleared: bool, event_fee_cleared: bool|null, mcq_fee_cleared: bool|null}
     */
    public function payload(Tenant $school, ?FestEvent $event = null, ?McqExam $exam = null, ?int $headId = null, ?int $phaseId = null, ?int $batchId = null, string $documentType = 'default'): array
    {
        $membershipCleared = $this->membershipFeeCleared($school);
        $eventFeeCleared = $event ? $this->festEventFeeCleared($event, $school, $headId, $phaseId, $batchId, $documentType) : null;
        $mcqFeeCleared = $exam ? $this->mcqExamFeeCleared($exam, $school) : null;

        $reason = null;
        if (! $membershipCleared) {
            // Kept self-contained (not just "...is pending.") since the caller (Vue banner)
            // shows this reason alone — it used to always append a generic "membership AND
            // event fee" sentence regardless of which check actually failed, which was wrong
            // whenever only membership was the blocker.
            $reason = 'Sahodaya membership fee payment is pending. Pay and get it verified before downloading ID cards or hall tickets.';
        } elseif ($event && ! $eventFeeCleared) {
            $batchScoped = $batchId !== null && $event->usesPhasedRegionalBilling();
            $feeQuery = \App\Models\FestSchoolEventFee::where('event_id', $batchScoped ? $event->rootEvent()->id : $event->id)
                ->where('school_id', $school->id);
            if ($batchScoped) {
                $feeQuery->where('registration_batch_id', $batchId);
            } elseif ($headId !== null && $this->festFees->usesPerHeadBilling($event)) {
                $feeQuery->where('head_id', $headId);
            } elseif ($phaseId !== null && $this->festFees->usesPerPhaseBilling($event)) {
                $feeQuery->where('phase_id', $phaseId);
            }
            $fee = $feeQuery->first();
            if ($fee && $fee->status === 'proof_uploaded') {
                $reason = $batchScoped
                    ? $this->batchLabel($event, $batchId).' fee payment proof is uploaded and awaiting Sahodaya approval. ID card downloads unlock automatically right after approval.'
                    : 'Event fee payment proof is uploaded and awaiting Sahodaya approval. ID card downloads unlock automatically right after approval.';
            } elseif ($batchScoped) {
                $reason = $this->batchLabel($event, $batchId).' fee payment is pending. Upload payment proof for this level and get it approved to unlock ID card downloads.';
            } elseif ($phaseId !== null && $this->festFees->usesPerPhaseBilling($event)) {
                $reason = 'This phase\'s fee payment is pending. Upload payment proof and get it approved to unlock ID card downloads.';
            } else {
                $reason = 'Event fee payment is pending. Upload payment proof and get it approved to unlock ID card downloads.';
            }
        } elseif ($exam && ! $mcqFeeCleared) {
            $fee = \App\Models\McqSchoolFee::where('exam_id', $exam->id)->where('school_id', $school->id)->first();
            if ($fee && $fee->status === 'proof_uploaded') {
                $reason = 'Talent Search exam fee payment proof is uploaded and awaiting Sahodaya approval. Hall ticket downloads unlock automatically right after approval.';
            } else {
                $reason = 'Talent Search exam fee payment is pending. Upload payment proof and get it approved to unlock hall ticket downloads.';
            }
        }

        return [
            'blocked'             => $reason !== null,
            'reason'              => $reason,
            'membership_cleared'  => $membershipCleared,
            'event_fee_cleared'   => $eventFeeCleared,
            'mcq_fee_cleared'     => $mcqFeeCleared,
            'links'               => [
                'membership' => "/school-admin/{$school->id}/registration",
                'payments'   => "/school-admin/{$school->id}/payments",
            ],
        ];
    }

    /** Human label for one registration level, so gate messages name it ("Level 1 fee payment is pending."). */
    private function batchLabel(FestEvent $event, int $batchId): string
    {
        $name = \App\Models\FestRegistrationBatch::where('event_id', $event->rootEvent()->id)
            ->where('id', $batchId)
            ->value('name');

        return $name ?: 'This registration level';
    }
}
