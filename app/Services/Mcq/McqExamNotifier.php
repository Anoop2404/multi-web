<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
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
        $registration->loadMissing(['exam', 'student', 'teacher']);
        $schoolId = $registration->school_id;

        $service = app(NotificationService::class);
        $replacements = [
            'exam_title'   => $registration->exam->title,
            'student_name' => $registration->participantName() ?: 'Participant',
        ];

        foreach (User::role(['school_admin', 'school_staff'])->where('tenant_id', $schoolId)->get() as $user) {
            $service->notifyFromTemplate($user, 'mcq.registration.submitted', $replacements,
                "/school-admin/{$schoolId}/mcq/{$registration->exam_id}/register");
        }
    }

    public function registrationApproved(McqRegistration $registration): void
    {
        $registration->loadMissing(['exam', 'student', 'teacher']);
        $schoolId = $registration->school_id;

        $service = app(NotificationService::class);
        $replacements = [
            'exam_title'     => $registration->exam->title,
            'student_name'   => $registration->participantName() ?: 'Participant',
            'hall_ticket_no' => $registration->hall_ticket_no ?? '',
        ];

        foreach (User::role(['school_admin', 'school_staff'])->where('tenant_id', $schoolId)->get() as $user) {
            $service->notifyFromTemplate($user, 'mcq.registration.confirmed', $replacements,
                "/school-admin/{$schoolId}/mcq/{$registration->exam_id}/hall-tickets");
        }

        if ($registration->isTeacherRegistration()) {
            $userId = $registration->teacher?->user_id;
            $user = $userId ? User::find($userId) : null;
            if ($user) {
                $service->notifyFromTemplate($user, 'mcq.hall_ticket.issued', $replacements,
                    "/portal/teacher/{$schoolId}/exams");
            }
        } else {
            $studentUser = $registration->student?->user;
            if ($studentUser) {
                $service->notifyFromTemplate($studentUser, 'mcq.hall_ticket.issued', $replacements,
                    "/portal/student/{$schoolId}/mcq");
            }
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
        $registration->loadMissing(['exam', 'student', 'teacher']);
        $this->notifySchoolAdmins($registration, 'mcq.fee.rejected', [
            'exam_title'   => $registration->exam->title,
            'student_name' => $registration->participantName() ?: 'Participant',
            'reason'       => $reason ?? 'Contact your Sahodaya for details.',
        ]);
    }

    public function schoolBatchFeeApproved(McqSchoolFee $schoolFee): void
    {
        $schoolFee->loadMissing(['exam', 'school']);
        $schoolId = $schoolFee->school_id;
        $service = app(NotificationService::class);
        $replacements = [
            'exam_title'   => $schoolFee->exam?->title ?? 'Talent Search Exam',
            'student_name' => $schoolFee->school?->name ?? 'School',
            'reason'       => '',
        ];

        foreach (User::role(['school_admin', 'school_staff'])->where('tenant_id', $schoolId)->get() as $user) {
            $service->notifyFromTemplate(
                $user,
                'mcq.fee.approved',
                $replacements,
                "/school-admin/{$schoolId}/mcq/{$schoolFee->exam_id}/fee",
            );
        }
    }

    public function schoolBatchFeeRejected(McqSchoolFee $schoolFee, ?string $reason = null): void
    {
        $schoolFee->loadMissing(['exam', 'school']);
        $schoolId = $schoolFee->school_id;
        $service = app(NotificationService::class);
        $replacements = [
            'exam_title'   => $schoolFee->exam?->title ?? 'Talent Search Exam',
            'student_name' => $schoolFee->school?->name ?? 'School',
            'reason'       => $reason ?? 'Contact your Sahodaya for details.',
        ];

        foreach (User::role(['school_admin', 'school_staff'])->where('tenant_id', $schoolId)->get() as $user) {
            $service->notifyFromTemplate(
                $user,
                'mcq.fee.rejected',
                $replacements,
                "/school-admin/{$schoolId}/mcq/{$schoolFee->exam_id}/fee",
            );
        }
    }

    public function examReminder(McqRegistration $registration): bool
    {
        $registration->loadMissing(['exam', 'student.user', 'teacher']);
        $exam = $registration->exam;
        if (! $exam?->scheduled_at) {
            return false;
        }

        $service = app(NotificationService::class);
        $replacements = [
            'exam_title'   => $exam->title,
            'scheduled_at' => $exam->scheduled_at->format('j F Y, g:i A'),
            'venue'        => $exam->venue ?? '',
            'student_name' => $registration->participantName() ?: 'Participant',
        ];

        $sent = false;

        if ($registration->isTeacherRegistration()) {
            $userId = $registration->teacher?->user_id;
            $user = $userId ? User::find($userId) : null;
            if ($user) {
                $service->notifyFromTemplate(
                    $user,
                    'mcq.exam.reminder',
                    $replacements,
                    "/portal/teacher/{$registration->school_id}/exams",
                );
                $sent = true;
            }
        } else {
            $studentUser = $registration->student?->user;
            if ($studentUser) {
                $service->notifyFromTemplate(
                    $studentUser,
                    'mcq.exam.reminder',
                    $replacements,
                    "/portal/student/{$registration->school_id}/mcq",
                );
                $sent = true;
            }
        }

        foreach (User::role(['school_admin', 'school_staff'])->where('tenant_id', $registration->school_id)->get() as $user) {
            $service->notifyFromTemplate(
                $user,
                'mcq.exam.reminder',
                $replacements,
                "/school-admin/{$registration->school_id}/mcq/{$exam->id}/hall-tickets",
            );
            $sent = true;
        }

        return $sent;
    }

    public function certificateReady(McqRegistration $registration): void
    {
        $registration->loadMissing(['exam', 'student.user', 'teacher']);
        $exam = $registration->exam;
        $service = app(NotificationService::class);
        $replacements = [
            'exam_title'   => $exam?->title ?? 'Talent Search exam',
            'student_name' => $registration->participantName() ?: 'Participant',
        ];

        if ($registration->isTeacherRegistration()) {
            $userId = $registration->teacher?->user_id;
            $user = $userId ? User::find($userId) : null;
            if ($user) {
                $service->notifyFromTemplate(
                    $user,
                    'mcq.certificate.ready',
                    $replacements,
                    "/portal/teacher/{$registration->school_id}/exams",
                );
            }
        } else {
            $studentUser = $registration->student?->user;
            if ($studentUser) {
                $service->notifyFromTemplate(
                    $studentUser,
                    'mcq.certificate.ready',
                    $replacements,
                    "/portal/student/{$registration->school_id}/mcq",
                );
            }
        }
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

    /**
     * Notify Sahodaya admin users when a school cancels one of their MCQ registrations.
     * Fires alongside the school-side audit to close the "Sahodaya never knows" gap.
     */
    public function registrationCancelledBySchool(McqRegistration $registration): void
    {
        $registration->loadMissing(['exam', 'student', 'teacher']);
        $exam = $registration->exam;
        if (! $exam) {
            return;
        }

        $sahodayaId = $exam->tenant_id;
        $service = app(NotificationService::class);
        $replacements = [
            'exam_title'   => $exam->title,
            'student_name' => $registration->participantName() ?: 'Participant',
            'school_name'  => '', // resolved per user context
        ];
        $url = "/sahodaya-admin/{$sahodayaId}/mcq-exams/{$exam->id}/registrations";

        foreach (User::role(['sahodaya_admin', 'sahodaya_staff'])->where('tenant_id', $sahodayaId)->get() as $user) {
            try {
                $service->notifyFromTemplate($user, 'mcq.registration.cancelled_by_school', $replacements, $url);
            } catch (\Throwable) {
                // non-blocking
            }
        }
    }
    public function registrationCancelledByAdmin(McqRegistration $registration, string $reason): void
    {
        $registration->loadMissing(['exam', 'student', 'teacher']);
        $exam = $registration->exam;
        if (! $exam) {
            return;
        }

        $replacements = [
            'exam_title'       => $exam->title,
            'student_name'     => $registration->participantName() ?: 'Participant',
            'rejection_reason' => $reason,
        ];

        $this->notifySchoolAdmins($registration, 'mcq.registration.cancelled_admin', $replacements);
    }
    public function examCancelled(McqExam $exam, \Illuminate\Support\Collection $credits): void
    {
        $schoolIds = McqRegistration::where('exam_id', $exam->id)
            ->distinct()
            ->pluck('school_id');

        $service = app(NotificationService::class);
        $creditsBySchool = $credits->keyBy(function($c) {
            return $c->creditable?->school_id;
        });

        foreach ($schoolIds as $schoolId) {
            $creditForSchool = $creditsBySchool->get($schoolId);
            $replacements = [
                'exam_title' => $exam->title,
                'credit_line' => $creditForSchool && $creditForSchool->amount > 0
                    ? " A fee credit of ₹{$creditForSchool->amount} has been recorded to your school account."
                    : '',
            ];

            foreach (User::role(['school_admin', 'school_staff'])->where('tenant_id', $schoolId)->get() as $user) {
                try {
                    $service->notifyFromTemplate($user, 'mcq.exam.cancelled', $replacements);
                } catch (\Throwable) {
                }
            }
        }
    }
}
