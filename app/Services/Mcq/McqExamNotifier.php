<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\User;
use App\Services\Notifications\NotificationService;

class McqExamNotifier
{
    public function resultsPublished(McqExam $exam): void
    {
        $schoolIds = McqRegistration::where('exam_id', $exam->id)
            ->distinct()
            ->pluck('school_id');

        $service = app(NotificationService::class);

        foreach ($schoolIds as $schoolId) {
            $replacements = ['exam_title' => $exam->title];

            foreach (User::role(['school_admin', 'school_staff'])->where('tenant_id', $schoolId)->get() as $user) {
                $service->notifyFromTemplate($user, 'mcq.results.published', $replacements,
                    "/school-admin/{$schoolId}/mcq/{$exam->id}/results");
            }

            foreach (User::role('teacher')->where('tenant_id', $schoolId)->get() as $user) {
                $service->notifyFromTemplate($user, 'mcq.results.published', $replacements,
                    "/portal/teacher/{$schoolId}");
            }
        }

        McqRegistration::where('exam_id', $exam->id)
            ->with(['student.user'])
            ->get()
            ->each(function (McqRegistration $registration) use ($service, $exam) {
                $user = $registration->student?->user;
                if (! $user) {
                    return;
                }

                $service->notifyFromTemplate($user, 'mcq.results.published', [
                    'exam_title' => $exam->title,
                ], "/portal/student/{$registration->school_id}/mcq");
            });
    }

    public function registrationConfirmed(McqRegistration $registration): void
    {
        $registration->loadMissing(['exam', 'student']);
        $schoolId = $registration->school_id;

        $service = app(NotificationService::class);
        $replacements = [
            'exam_title'   => $registration->exam->title,
            'student_name' => $registration->student?->name ?? 'Student',
        ];

        foreach (User::role(['school_admin', 'school_staff'])->where('tenant_id', $schoolId)->get() as $user) {
            $service->notifyFromTemplate($user, 'mcq.registration.submitted', $replacements,
                "/school-admin/{$schoolId}/mcq/{$registration->exam_id}/register");
        }
    }

    public function registrationApproved(McqRegistration $registration): void
    {
        $registration->loadMissing(['exam', 'student']);
        $schoolId = $registration->school_id;

        $service = app(NotificationService::class);
        $replacements = [
            'exam_title'     => $registration->exam->title,
            'student_name'   => $registration->student?->name ?? 'Student',
            'hall_ticket_no' => $registration->hall_ticket_no ?? '',
        ];

        foreach (User::role(['school_admin', 'school_staff'])->where('tenant_id', $schoolId)->get() as $user) {
            $service->notifyFromTemplate($user, 'mcq.registration.confirmed', $replacements,
                "/school-admin/{$schoolId}/mcq/{$registration->exam_id}/hall-tickets");
        }

        $studentUser = $registration->student?->user;
        if ($studentUser) {
            $service->notifyFromTemplate($studentUser, 'mcq.hall_ticket.issued', $replacements,
                "/portal/student/{$schoolId}/mcq");
        }
    }

    public function feeApproved(McqRegistration $registration): void
    {
        $registration->loadMissing(['exam', 'student']);
        $this->notifySchoolAdmins($registration, 'mcq.fee.approved', [
            'exam_title'   => $registration->exam->title,
            'student_name' => $registration->student?->name ?? 'Student',
        ]);
    }

    public function feeRejected(McqRegistration $registration, ?string $reason = null): void
    {
        $registration->loadMissing(['exam', 'student']);
        $this->notifySchoolAdmins($registration, 'mcq.fee.rejected', [
            'exam_title'   => $registration->exam->title,
            'student_name' => $registration->student?->name ?? 'Student',
            'reason'       => $reason ?? 'Contact your Sahodaya for details.',
        ]);
    }

    private function notifySchoolAdmins(McqRegistration $registration, string $template, array $replacements): void
    {
        $schoolId = $registration->school_id;
        $service = app(NotificationService::class);

        foreach (User::role(['school_admin', 'school_staff'])->where('tenant_id', $schoolId)->get() as $user) {
            $service->notifyFromTemplate($user, $template, $replacements,
                "/school-admin/{$schoolId}/mcq/{$registration->exam_id}/fee");
        }
    }
}
