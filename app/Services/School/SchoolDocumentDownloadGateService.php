<?php

namespace App\Services\School;

use App\Models\FestEvent;
use App\Models\McqExam;
use App\Models\McqSchoolFee;
use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\Tenant;
use App\Services\Events\FestSchoolEventFeeService;
use App\Support\AcademicYear;

class SchoolDocumentDownloadGateService
{
    public function __construct(
        private FestSchoolEventFeeService $festFees,
    ) {}

    /** Sahodaya annual membership fee verified for the current academic year. */
    public function membershipFeeCleared(Tenant $school): bool
    {
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
     */
    public function festEventFeeCleared(FestEvent $event, Tenant $school, ?int $headId = null): bool
    {
        if ($headId !== null && $this->festFees->usesPerHeadBilling($event)) {
            return $this->festFees->isHeadPaid($event, $school->id, $headId);
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

    public function assertFestEventFeeForDownloads(FestEvent $event, Tenant $school, ?int $headId = null): void
    {
        $this->assertMembershipFeeForDownloads($school);

        if ($this->festEventFeeCleared($event, $school, $headId)) {
            return;
        }

        $message = $headId !== null && $this->festFees->usesPerHeadBilling($event)
            ? 'Event Head fee payment is pending. Upload payment proof for this head and wait for verification before downloading ID cards or hall tickets.'
            : 'Event fee payment is pending. Upload payment proof and wait for verification before downloading ID cards or hall tickets.';

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
     * @return array{blocked: bool, reason: ?string, membership_cleared: bool, event_fee_cleared: bool|null, mcq_fee_cleared: bool|null}
     */
    public function payload(Tenant $school, ?FestEvent $event = null, ?McqExam $exam = null): array
    {
        $membershipCleared = $this->membershipFeeCleared($school);
        $eventFeeCleared = $event ? $this->festEventFeeCleared($event, $school) : null;
        $mcqFeeCleared = $exam ? $this->mcqExamFeeCleared($exam, $school) : null;

        $reason = null;
        if (! $membershipCleared) {
            $reason = 'Sahodaya membership fee payment is pending.';
        } elseif ($event && ! $eventFeeCleared) {
            $fee = \App\Models\FestSchoolEventFee::where('event_id', $event->id)->where('school_id', $school->id)->first();
            if ($fee && $fee->status === 'proof_uploaded') {
                $reason = 'Event fee payment proof is uploaded and awaiting Sahodaya approval. ID card downloads unlock automatically right after approval.';
            } else {
                $reason = 'Event fee payment is pending. Upload payment proof and get it approved to unlock ID card downloads.';
            }
        } elseif ($exam && ! $mcqFeeCleared) {
            $fee = \App\Models\McqSchoolFee::where('exam_id', $exam->id)->where('school_id', $school->id)->first();
            if ($fee && $fee->status === 'proof_uploaded') {
                $reason = 'Talent Search exam fee payment proof is uploaded and awaiting Sahodaya approval.';
            } else {
                $reason = 'Talent Search exam fee payment is pending.';
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
}
