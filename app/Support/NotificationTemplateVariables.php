<?php

namespace App\Support;

/**
 * Reference list of the {{placeholder}} variables each notification template
 * slug is populated with, for the "Notification templates" editor UI. This is
 * a static, hand-maintained mirror of the placeholders used in each template's
 * original default text (see database/seeders/NotificationTemplatesSeeder.php)
 * and the replacement arrays passed to notifyFromTemplate() across the app.
 *
 * It intentionally does NOT derive from the live, possibly-edited
 * body_template in the database — an admin who deletes a placeholder from
 * their custom text shouldn't also lose the reference for what's available to
 * add back. If a slug is missing here, the editor UI falls back to showing
 * whatever {{...}} tokens are currently present in that template's own body.
 *
 * @see \Database\Seeders\NotificationTemplatesSeeder
 */
class NotificationTemplateVariables
{
    /** @return array<string, list<string>> */
    public static function map(): array
    {
        return [
            'fest.event.completed'         => ['event_title', 'competition_label'],
            'fest.registration.approved'   => ['event_title', 'item_title'],
            'fest.registration.rejected'   => ['event_title'],
            'fest.registration.withdrawn'  => ['event_title', 'item_title'],
            'fest.results.published'       => ['event_title'],
            'fest.promotion.completed'     => ['count', 'from_title', 'event_title'],
            'fest.registration.deadline'   => ['event_title', 'close_date', 'days_left'],
            'fest.registration.open'       => ['event_title', 'competition_label', 'close_date'],
            'fest.payment.pending'         => ['amount', 'event_title'],
            'fest.competition.reminder'    => ['event_title', 'start_date', 'venue'],
            'fest.certificate.available'   => ['count', 'event_title'],
            'fest.record.broken'           => ['student_name', 'school_name', 'item_title', 'event_title', 'new_value', 'record_unit', 'prize_label'],
            'fest.schedule.published'      => ['event_title'],
            'fest.appeal.received'         => ['participant_name', 'event_title'],
            'fest.chest_numbers.revealed'  => ['participant_name', 'event_title'],
            'sports.winners.received'      => ['school_name', 'count', 'event_title'],

            'training.registration.confirmed' => ['program_title'],
            'training.pending_school.linked'  => ['pending_school_name', 'school_name', 'program_title'],
            'training.pending_school.rejected'=> ['pending_school_name', 'program_title', 'reason'],
            'training.fee.approved'        => ['program_title'],
            'training.fee.rejected'        => ['program_title', 'reason'],
            'training.payment.reminder'    => ['amount', 'program_title'],
            'training.reminder'            => ['program_title', 'start_date', 'venue'],
            'training.registration.closing'=> ['program_title', 'close_date', 'days_left', 'venue', 'start_date'],
            'training.session.reminder'    => ['session_title', 'program_title', 'scheduled_at', 'venue'],
            'training.certificate.available'=> ['program_title', 'teacher_name'],
            'training.waitlist.promoted'   => ['program_title', 'status', 'teacher_name'],

            'mcq.results.published'                  => ['exam_title'],
            'mcq.registration.confirmed'              => ['student_name', 'exam_title'],
            'mcq.registration.submitted'               => ['student_name', 'exam_title'],
            'mcq.hall_ticket.issued'                   => ['hall_ticket_no', 'student_name', 'exam_title'],
            'mcq.fee.approved'                         => ['student_name', 'exam_title'],
            'mcq.fee.rejected'                          => ['student_name', 'exam_title', 'reason'],
            'mcq.attendance_correction.requested'       => ['requested_by', 'student_name', 'exam_title', 'requested_status'],
            'mcq.attendance_correction.approved'        => ['student_name', 'exam_title', 'requested_status'],
            'mcq.attendance_correction.rejected'         => ['student_name', 'exam_title', 'reason'],
            'mcq.exam.reminder'                          => ['exam_title', 'scheduled_at', 'venue'],
            'mcq.certificate.ready'                      => ['exam_title'],

            'student.portal.provisioned'   => ['school_name', 'login_url', 'login_email'],
            'teacher.portal.provisioned'   => ['school_name', 'login_url', 'login_email'],
            'teacher.verification.pending' => ['teacher_name'],
            'teacher.verification.approved'=> ['teacher_name'],
            'teacher.verification.rejected'=> ['teacher_name', 'reason'],
            'teacher.verification.required'=> ['teacher_name'],
            'student.verification.pending' => ['student_name'],
            'student.verification.approved'=> ['student_name'],
            'student.verification.rejected'=> ['student_name', 'reason'],
            'student.verification.required'=> ['student_name'],

            'circular.published'           => ['circular_title'],
            'payment.proof.uploaded'       => ['school_name', 'context_label'],
            'membership.payment.submitted' => ['school_name', 'academic_year'],
            'membership.payment.approved'  => ['academic_year'],
            'membership.payment.rejected'  => ['academic_year', 'reason'],
            'membership.data.rejected'     => ['academic_year', 'reason'],

            'state.remittance.created'     => ['title', 'amount'],
            'state.remittance.verified'    => ['title', 'amount'],
            'state.remittance.rejected'    => ['title', 'reason'],

            'board_results.upload_reminder'          => ['class', 'examination_type', 'academic_year'],
            'board_results.submission_confirmation'  => ['class', 'examination_type', 'academic_year'],
            'board_results.verification_pending'      => ['school_name', 'class', 'examination_type', 'academic_year'],
            'board_results.result_approved'           => ['class', 'examination_type', 'academic_year'],
            'board_results.result_rejected'            => ['class', 'examination_type', 'academic_year', 'reason'],
            'board_results.result_published'           => ['class', 'examination_type', 'academic_year', 'pass_percent'],

            'email.membership.application_submitted'          => ['school_name'],
            'email.membership.credentials_issued'              => ['school_name', 'sahodaya_name'],
            'email.membership.school_approved'                 => ['school_name', 'sahodaya_name'],
            'email.membership.school_rejected'                 => ['school_name', 'sahodaya_name'],
            'email.membership.data_submitted'                  => ['school_name', 'academic_year'],
            'email.membership.data_approved'                   => ['academic_year', 'sahodaya_name'],
            'email.membership.data_rejected'                   => ['academic_year'],
            'email.membership.payment_submitted'               => ['school_name', 'academic_year'],
            'email.membership.payment_verified'                => ['academic_year', 'sahodaya_name'],
            'email.membership.payment_rejected'                => ['academic_year', 'sahodaya_name'],
            'email.membership.registration_completed_first'    => ['academic_year', 'sahodaya_name'],
            'email.membership.registration_completed_renewal'  => ['academic_year'],
            'email.membership.reminder_window_closing'         => ['academic_year', 'days_left'],
            'email.membership.reminder_payment_due'            => ['academic_year', 'amount'],
            'email.fees.receipt_approved'                      => ['context_title', 'sahodaya_name', 'receipt_no'],
            'email.admission_enquiry'                          => ['school_name'],
            'email.auth.verify_email'                          => ['school_name', 'sahodaya_name'],
            'email.auth.reset_password'                        => ['school_name', 'sahodaya_name'],
        ];
    }

    /** @return list<string> */
    public static function forSlug(string $slug): array
    {
        return self::map()[$slug] ?? [];
    }

    /** Sample values used to render a preview / test email — generic, not tenant data. */
    public static function sampleValue(string $variable): string
    {
        return match ($variable) {
            'event_title', 'program_title' => 'Sample Sports Meet 2026',
            'exam_title'      => 'Sample Talent Search Exam',
            'item_title'      => '100m Sprint',
            'competition_label' => 'Sahodaya round',
            'close_date', 'start_date' => '31 Jul 2026',
            'days_left'       => '3',
            'amount'          => '500.00',
            'venue'           => 'Sample Auditorium',
            'count'           => '5',
            'school_name'     => 'Sample School',
            'student_name'    => 'Sample Student',
            'teacher_name'    => 'Sample Teacher',
            'participant_name'=> 'Sample Participant',
            'reason'          => 'Sample reason text',
            'academic_year'   => '2025-26',
            'circular_title'  => 'Sample Circular',
            'login_url'       => 'https://example.org/portal/login',
            'login_email'     => 'sample@example.org',
            'hall_ticket_no'  => 'HT-0001',
            'requested_by'    => 'Sample Teacher',
            'requested_status'=> 'present',
            'scheduled_at'    => '31 Jul 2026, 10:00 AM',
            'session_title'   => 'Session 1',
            'status'          => 'confirmed',
            'from_title'      => 'School round',
            'new_value'       => '11.2',
            'record_unit'     => 'seconds',
            'prize_label'     => 'Gold',
            'title'           => 'Sample Remittance',
            'context_label'   => 'Sample context',
            'class'           => '10',
            'examination_type'=> 'SSLC',
            'pass_percent'    => '95',
            'sahodaya_name'   => 'Sample Sahodaya',
            'context_title'   => 'Sample Fee Payment',
            'receipt_no'      => 'RCPT-0001',
            default           => 'Sample value',
        };
    }
}
