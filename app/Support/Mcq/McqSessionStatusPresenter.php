<?php

namespace App\Support\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Services\Mcq\McqExamSessionService;

class McqSessionStatusPresenter
{
    /** @return array{key: string, label: string, tone: string} */
    public static function forRegistration(McqRegistration $reg, McqExam $exam): array
    {
        if ($reg->attendance_status === 'absent') {
            return ['key' => 'absent', 'label' => 'Absent', 'tone' => 'danger'];
        }

        if ($reg->status === 'submitted') {
            return ['key' => 'submitted', 'label' => 'Submitted', 'tone' => 'success'];
        }

        if ($reg->started_at) {
            $session = app(McqExamSessionService::class);
            if ($session->isExpired($reg)) {
                return ['key' => 'expired', 'label' => 'Time expired', 'tone' => 'warning'];
            }

            return ['key' => 'started', 'label' => 'In progress', 'tone' => 'info'];
        }

        if ($exam->isOnlineDelivery()) {
            return ['key' => 'not_started', 'label' => 'Not started', 'tone' => 'neutral'];
        }

        return ['key' => 'registered', 'label' => 'Registered', 'tone' => 'neutral'];
    }
}
