<?php

namespace App\Support\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;

class McqRegistrationStatusPresenter
{
    /** @return array{key: string, label: string, tone: string} */
    public static function forRegistration(McqRegistration $reg, McqExam $exam, ?\App\Models\McqSchoolFee $schoolFee = null): array
    {
        if ($reg->attendance_status === 'absent') {
            return ['key' => 'absent', 'label' => 'Absent', 'tone' => 'danger'];
        }

        if ($exam->results_published && $reg->mark) {
            return ['key' => 'published', 'label' => 'Result published', 'tone' => 'success'];
        }

        if ($reg->status === 'submitted' && $reg->mark) {
            return ['key' => 'awaiting_publish', 'label' => 'Awaiting result publish', 'tone' => 'warning'];
        }

        if ($reg->attendance_status === 'present') {
            return ['key' => 'attended', 'label' => 'Attended', 'tone' => 'info'];
        }

        if ($reg->hall_ticket_no && $reg->isApproved()) {
            return ['key' => 'ticket_issued', 'label' => 'Hall ticket issued', 'tone' => 'success'];
        }

        if ($reg->approval_status === 'pending_payment') {
            if ($reg->feeReceipt?->status === 'uploaded' || $schoolFee?->status === 'proof_uploaded') {
                return ['key' => 'payment_uploaded', 'label' => 'Payment under review', 'tone' => 'info'];
            }

            if ($schoolFee?->status === 'partial') {
                return ['key' => 'partial_payment', 'label' => 'Partial payment received', 'tone' => 'warning'];
            }

            if ($reg->feeReceipt?->status === 'rejected' || ($schoolFee?->feeReceipt?->status === 'rejected' && $schoolFee?->status !== 'approved')) {
                return ['key' => 'payment_rejected', 'label' => 'Payment proof rejected', 'tone' => 'danger'];
            }

            return ['key' => 'fee_pending', 'label' => 'Fee pending', 'tone' => 'warning'];
        }

        if ($reg->approval_status === 'pending_approval') {
            return ['key' => 'awaiting_approval', 'label' => 'Awaiting Sahodaya approval', 'tone' => 'warning'];
        }

        if ($reg->approval_status === 'rejected') {
            return ['key' => 'rejected', 'label' => 'Rejected', 'tone' => 'danger'];
        }

        if ($reg->isApproved()) {
            return ['key' => 'approved', 'label' => 'Approved', 'tone' => 'success'];
        }

        return ['key' => 'registered', 'label' => 'Registered', 'tone' => 'neutral'];
    }
}
