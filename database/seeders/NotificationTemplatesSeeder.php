<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Stancl\Tenancy\Exceptions\TenantDatabaseDoesNotExistException;

class NotificationTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = $this->templates();

        $seed = function () use ($templates): void {
            foreach ($templates as $template) {
                NotificationTemplate::updateOrCreate(
                    ['slug' => $template['slug']],
                    array_merge($template, ['is_active' => true, 'channels_json' => ['in_app', 'email']])
                );
            }
        };

        if (tenancy()->initialized) {
            $seed();

            return;
        }

        $sahodayas = Tenant::where('type', 'sahodaya')->get();
        if ($sahodayas->isEmpty()) {
            $this->command?->warn('NotificationTemplatesSeeder: no Sahodaya tenants yet — skipped.');

            return;
        }

        foreach ($sahodayas as $sahodaya) {
            $database = $sahodaya->database()->getName();
            if (! $sahodaya->database()->manager()->databaseExists($database)) {
                $this->command?->warn("NotificationTemplatesSeeder: skipped {$sahodaya->subdomain} (DB missing: {$database})");

                continue;
            }

            try {
                $sahodaya->run($seed);
            } catch (TenantDatabaseDoesNotExistException) {
                $this->command?->warn("NotificationTemplatesSeeder: skipped {$sahodaya->subdomain} (DB not reachable)");
            }
        }
    }

    /** @return list<array{slug: string, title: string, body_template: string}> */
    private function templates(): array
    {
        return [
            [
                'slug'          => 'fest.event.completed',
                'title'         => 'Event completed',
                'body_template' => '{{event_title}} ({{competition_label}}) is now marked complete. Results, certificates, and ID cards remain available for download.',
            ],
            [
                'slug'          => 'fest.registration.approved',
                'title'         => 'Event registration approved',
                'body_template' => 'Your registration for {{event_title}} ({{item_title}}) has been approved.',
            ],
            [
                'slug'          => 'fest.registration.rejected',
                'title'         => 'Event registration rejected',
                'body_template' => 'Your registration for {{event_title}} was not approved. Contact your Sahodaya for details.',
            ],
            [
                'slug'          => 'fest.registration.withdrawn',
                'title'         => 'Event registration cancelled',
                'body_template' => 'Registration for {{event_title}} ({{item_title}}) has been cancelled.',
            ],
            [
                'slug'          => 'fest.results.published',
                'title'         => 'Event results published',
                'body_template' => 'Results for {{event_title}} are now published.',
            ],
            [
                'slug'          => 'fest.promotion.completed',
                'title'         => 'Winners promoted to next level',
                'body_template' => '{{count}} participant(s) from {{from_title}} have been promoted to {{event_title}}.',
            ],
            [
                'slug'          => 'fest.registration.deadline',
                'title'         => 'Registration deadline approaching',
                'body_template' => 'Registration for {{event_title}} closes on {{close_date}} ({{days_left}} day(s) left).',
            ],
            [
                'slug'          => 'fest.registration.open',
                'title'         => 'Event registration open',
                'body_template' => 'Registration is now open for {{event_title}} ({{competition_label}}). Closes {{close_date}}.',
            ],
            [
                'slug'          => 'fest.payment.pending',
                'title'         => 'Event fee payment pending',
                'body_template' => 'Payment of ₹{{amount}} is still pending for {{event_title}}. Please upload fee proof from the school portal.',
            ],
            [
                'slug'          => 'fest.competition.reminder',
                'title'         => 'Competition reminder',
                'body_template' => 'Reminder: {{event_title}} starts on {{start_date}}. Venue: {{venue}}.',
            ],
            [
                'slug'          => 'fest.certificate.available',
                'title'         => 'Event certificates available',
                'body_template' => '{{count}} certificate(s) for {{event_title}} are now available to download.',
            ],
            [
                'slug'          => 'fest.record.broken',
                'title'         => 'Athletic record broken',
                'body_template' => '{{student_name}} ({{school_name}}) broke the record in {{item_title}} at {{event_title}} with {{new_value}} {{record_unit}}. Prize: {{prize_label}}.',
            ],
            [
                'slug'          => 'training.registration.confirmed',
                'title'         => 'Training registration confirmed',
                'body_template' => 'Your registration for {{program_title}} has been confirmed.',
            ],
            [
                'slug'          => 'mcq.results.published',
                'title'         => 'MCQ results published',
                'body_template' => 'Results for {{exam_title}} are now available.',
            ],
            [
                'slug'          => 'student.portal.provisioned',
                'title'         => 'Student portal account created',
                'body_template' => 'Your student portal account for {{school_name}} is ready. Sign in at {{login_url}} using {{login_email}} and the password provided by your school.',
            ],
            [
                'slug'          => 'teacher.portal.provisioned',
                'title'         => 'Teacher portal account created',
                'body_template' => 'Your teacher portal account for {{school_name}} is ready. Sign in at {{login_url}} using {{login_email}} and the password provided by your school.',
            ],
            [
                'slug'          => 'teacher.verification.pending',
                'title'         => 'Teacher awaiting verification',
                'body_template' => '{{teacher_name}} has been added and is awaiting Sahodaya verification.',
            ],
            [
                'slug'          => 'teacher.verification.approved',
                'title'         => 'Teacher verified',
                'body_template' => '{{teacher_name}} has been verified by your Sahodaya.',
            ],
            [
                'slug'          => 'teacher.verification.rejected',
                'title'         => 'Teacher verification rejected',
                'body_template' => 'Verification for {{teacher_name}} was rejected. Reason: {{reason}}',
            ],
            [
                'slug'          => 'teacher.verification.required',
                'title'         => 'Teacher needs re-verification',
                'body_template' => '{{teacher_name}} was edited and now requires re-verification by your Sahodaya.',
            ],
            [
                'slug'          => 'student.verification.pending',
                'title'         => 'Student awaiting verification',
                'body_template' => '{{student_name}} has been added and is awaiting Sahodaya verification.',
            ],
            [
                'slug'          => 'student.verification.approved',
                'title'         => 'Student verified',
                'body_template' => '{{student_name}} has been verified by your Sahodaya.',
            ],
            [
                'slug'          => 'student.verification.rejected',
                'title'         => 'Student verification rejected',
                'body_template' => 'Verification for {{student_name}} was rejected. Reason: {{reason}}',
            ],
            [
                'slug'          => 'student.verification.required',
                'title'         => 'Student needs re-verification',
                'body_template' => '{{student_name}} was edited and now requires re-verification by your Sahodaya.',
            ],
            [
                'slug'          => 'circular.published',
                'title'         => 'New circular',
                'body_template' => 'A new circular "{{circular_title}}" has been issued by your Sahodaya.',
            ],
            [
                'slug'          => 'payment.proof.uploaded',
                'title'         => 'Payment proof uploaded',
                'body_template' => '{{school_name}} uploaded payment proof for {{context_label}}.',
            ],
            [
                'slug'          => 'membership.payment.submitted',
                'title'         => 'Membership payment submitted',
                'body_template' => '{{school_name}} submitted membership payment for {{academic_year}}.',
            ],
            [
                'slug'          => 'state.remittance.created',
                'title'         => 'State remittance demand',
                'body_template' => 'State has issued a remittance demand: {{title}} — ₹{{amount}}.',
            ],
            [
                'slug'          => 'state.remittance.verified',
                'title'         => 'State remittance verified',
                'body_template' => 'Your payment for {{title}} (₹{{amount}}) has been verified by state.',
            ],
            [
                'slug'          => 'state.remittance.rejected',
                'title'         => 'State remittance rejected',
                'body_template' => 'Payment proof for {{title}} was rejected. Reason: {{reason}}.',
            ],
            [
                'slug'          => 'fest.schedule.published',
                'title'         => 'Event schedule published',
                'body_template' => 'The schedule for {{event_title}} is now published. Check fest day details and participant timings.',
            ],
            [
                'slug'          => 'mcq.registration.confirmed',
                'title'         => 'MCQ registration confirmed',
                'body_template' => '{{student_name}} has been registered for {{exam_title}}.',
            ],
            [
                'slug'          => 'mcq.registration.submitted',
                'title'         => 'Talent Search registration submitted',
                'body_template' => '{{student_name}} submitted a registration for {{exam_title}} and is awaiting fee confirmation.',
            ],
            [
                'slug'          => 'mcq.hall_ticket.issued',
                'title'         => 'Talent Search hall ticket issued',
                'body_template' => 'Hall ticket {{hall_ticket_no}} for {{student_name}} ({{exam_title}}) is ready.',
            ],
            [
                'slug'          => 'mcq.fee.approved',
                'title'         => 'MCQ fee approved',
                'body_template' => 'Fee proof for {{student_name}} ({{exam_title}}) was approved.',
            ],
            [
                'slug'          => 'mcq.fee.rejected',
                'title'         => 'MCQ fee rejected',
                'body_template' => 'Fee proof for {{student_name}} ({{exam_title}}) was rejected. {{reason}}',
            ],
            [
                'slug'          => 'training.pending_school.linked',
                'title'         => 'Training school linked',
                'body_template' => 'Your pending school "{{pending_school_name}}" was linked to {{school_name}} for {{program_title}}.',
            ],
            [
                'slug'          => 'training.pending_school.rejected',
                'title'         => 'Training school request rejected',
                'body_template' => 'Your pending school "{{pending_school_name}}" for {{program_title}} was rejected. {{reason}}',
            ],
            [
                'slug'          => 'membership.payment.approved',
                'title'         => 'Membership payment approved',
                'body_template' => 'Your membership payment for {{academic_year}} has been approved.',
            ],
            [
                'slug'          => 'membership.payment.rejected',
                'title'         => 'Membership payment rejected',
                'body_template' => 'Your membership payment for {{academic_year}} was rejected. {{reason}}',
            ],
            [
                'slug'          => 'membership.data.rejected',
                'title'         => 'Annual data submission rejected',
                'body_template' => 'Your annual data for {{academic_year}} was rejected. {{reason}}',
            ],
            [
                'slug'          => 'sports.winners.received',
                'title'         => 'Sports winners submitted',
                'body_template' => '{{school_name}} submitted {{count}} sports winner(s) for {{event_title}}.',
            ],
            [
                'slug'          => 'training.fee.approved',
                'title'         => 'Training fee approved',
                'body_template' => 'Fee proof for {{program_title}} was approved.',
            ],
            [
                'slug'          => 'training.fee.rejected',
                'title'         => 'Training fee rejected',
                'body_template' => 'Fee proof for {{program_title}} was rejected. {{reason}}',
            ],
            [
                'slug'          => 'training.payment.reminder',
                'title'         => 'Training fee payment reminder',
                'body_template' => 'Payment of ₹{{amount}} is still pending for {{program_title}}. Please upload or complete your fee payment.',
            ],
            [
                'slug'          => 'training.reminder',
                'title'         => 'Training program reminder',
                'body_template' => 'Reminder: {{program_title}} starts on {{start_date}}. Venue: {{venue}}.',
            ],
            [
                'slug'          => 'training.registration.closing',
                'title'         => 'Training registration closing soon',
                'body_template' => 'Registration for {{program_title}} closes on {{close_date}} ({{days_left}} day(s) left). Venue: {{venue}}. Start date: {{start_date}}.',
            ],
            [
                'slug'          => 'training.session.reminder',
                'title'         => 'Training session reminder',
                'body_template' => '{{session_title}} for {{program_title}} is scheduled at {{scheduled_at}}. Venue: {{venue}}.',
            ],
            [
                'slug'          => 'training.certificate.available',
                'title'         => 'Training certificate available',
                'body_template' => 'Your certificate for {{program_title}} is now available.',
            ],
            [
                'slug'          => 'training.waitlist.promoted',
                'title'         => 'Training waitlist — seat available',
                'body_template' => 'A seat opened for {{program_title}}. Your registration is now {{status}}. Welcome, {{teacher_name}}.',
            ],
            [
                'slug'          => 'fest.appeal.received',
                'title'         => 'Fest appeal received',
                'body_template' => 'An appeal was submitted for {{participant_name}} at {{event_title}}.',
            ],
            [
                'slug'          => 'fest.chest_numbers.revealed',
                'title'         => 'Chest number revealed',
                'body_template' => 'Chest number for {{participant_name}} at {{event_title}} has been revealed.',
            ],
            [
                'slug'          => 'mcq.attendance_correction.requested',
                'title'         => 'Attendance correction requested',
                'body_template' => '{{requested_by}} requested to change attendance for {{student_name}} ({{exam_title}}) to {{requested_status}}.',
            ],
            [
                'slug'          => 'mcq.attendance_correction.approved',
                'title'         => 'Attendance correction approved',
                'body_template' => 'Your attendance correction request for {{student_name}} ({{exam_title}}) was approved. New status: {{requested_status}}.',
            ],
            [
                'slug'          => 'mcq.attendance_correction.rejected',
                'title'         => 'Attendance correction rejected',
                'body_template' => 'Your attendance correction request for {{student_name}} ({{exam_title}}) was rejected. {{reason}}',
            ],
            [
                'slug'          => 'mcq.exam.reminder',
                'title'         => 'Talent Search exam reminder',
                'body_template' => 'Reminder: {{exam_title}} is scheduled on {{scheduled_at}}. Venue: {{venue}}. Please arrive on time with your hall ticket.',
            ],
            [
                'slug'          => 'mcq.certificate.ready',
                'title'         => 'Talent Search certificate ready',
                'body_template' => 'Your certificate for {{exam_title}} is now available.',
            ],
            [
                'slug'          => 'board_results.upload_reminder',
                'title'         => 'Board result upload reminder',
                'body_template' => 'Reminder: please upload and submit Class {{class}} ({{examination_type}}) board results for {{academic_year}}.',
            ],
            [
                'slug'          => 'board_results.submission_confirmation',
                'title'         => 'Board result submitted',
                'body_template' => 'Your Class {{class}} ({{examination_type}}) result for {{academic_year}} was submitted for Sahodaya verification.',
            ],
            [
                'slug'          => 'board_results.verification_pending',
                'title'         => 'Board result awaiting verification',
                'body_template' => '{{school_name}} submitted Class {{class}} ({{examination_type}}) results for {{academic_year}} and is awaiting verification.',
            ],
            [
                'slug'          => 'board_results.result_approved',
                'title'         => 'Board result approved',
                'body_template' => 'Your Class {{class}} ({{examination_type}}) result for {{academic_year}} has been approved by Sahodaya.',
            ],
            [
                'slug'          => 'board_results.result_rejected',
                'title'         => 'Board result rejected',
                'body_template' => 'Your Class {{class}} ({{examination_type}}) result for {{academic_year}} was rejected. Reason: {{reason}}',
            ],
            [
                'slug'          => 'board_results.result_published',
                'title'         => 'Board result published',
                'body_template' => 'Your Class {{class}} ({{examination_type}}) result for {{academic_year}} is now published. Pass %: {{pass_percent}}.',
            ],

            // --- Full HTML transactional emails (membership, fee receipts, auth) ---
            // These render inside branded Blade layouts (logo, buttons, tables) rather
            // than the plain-text notification wrapper; only the title/paragraph wording
            // is templated here, structural elements (tables, buttons) stay in the view.
            [
                'slug'          => 'email.membership.application_submitted',
                'title'         => 'New school application',
                'body_template' => 'A new school has submitted a membership application and is awaiting your review on the Sahodaya admin panel.',
            ],
            [
                'slug'          => 'email.membership.credentials_issued',
                'title'         => 'Your school portal is ready',
                'body_template' => '{{school_name}} has been registered with {{sahodaya_name}}. Use the credentials below to sign in and complete Gmail verification.',
            ],
            [
                'slug'          => 'email.membership.school_approved',
                'title'         => 'Membership approved',
                'body_template' => 'Great news! Your school {{school_name}} has been approved as a member of {{sahodaya_name}}.',
            ],
            [
                'slug'          => 'email.membership.school_rejected',
                'title'         => 'Application not approved',
                'body_template' => 'We regret to inform you that the membership application for {{school_name}} was not approved by {{sahodaya_name}}.',
            ],
            [
                'slug'          => 'email.membership.data_submitted',
                'title'         => 'Review annual submission',
                'body_template' => '{{school_name}} submitted student and/or teacher data for {{academic_year}}. Please review the submission in the Sahodaya admin panel.',
            ],
            [
                'slug'          => 'email.membership.data_approved',
                'title'         => 'Submission approved',
                'body_template' => 'Your annual data submission for {{academic_year}} has been approved by {{sahodaya_name}}. You can continue with membership payment.',
            ],
            [
                'slug'          => 'email.membership.data_rejected',
                'title'         => 'Submission needs correction',
                'body_template' => 'Your annual data submission for {{academic_year}} was rejected. Please review the reason below, make corrections, and resubmit.',
            ],
            [
                'slug'          => 'email.membership.payment_submitted',
                'title'         => 'Payment proof submitted',
                'body_template' => '{{school_name}} uploaded membership payment proof for {{academic_year}}. Please review and verify the payment.',
            ],
            [
                'slug'          => 'email.membership.payment_verified',
                'title'         => 'Membership payment approved',
                'body_template' => 'Your membership payment for {{academic_year}} has been verified by {{sahodaya_name}}. Your membership number is shown below.',
            ],
            [
                'slug'          => 'email.membership.payment_rejected',
                'title'         => 'Payment proof not accepted',
                'body_template' => 'Your payment proof for {{academic_year}} was rejected by {{sahodaya_name}}. Please upload a valid proof again.',
            ],
            [
                'slug'          => 'email.membership.registration_completed_first',
                'title'         => 'Welcome to the Sahodaya network',
                'body_template' => "Welcome! Your school's membership with {{sahodaya_name}} has been approved and your {{academic_year}} annual registration is complete.",
            ],
            [
                'slug'          => 'email.membership.registration_completed_renewal',
                'title'         => 'Annual membership active',
                'body_template' => 'Your {{academic_year}} annual Sahodaya membership registration is complete. Payment has been verified and your membership is now active.',
            ],
            [
                'slug'          => 'email.membership.reminder_window_closing',
                'title'         => 'Registration closing soon',
                'body_template' => 'Annual membership registration for {{academic_year}} closes in {{days_left}} day(s). Please begin registration in the school portal.',
            ],
            [
                'slug'          => 'email.membership.reminder_payment_due',
                'title'         => 'Payment due',
                'body_template' => 'Membership fee of ₹{{amount}} for {{academic_year}} is pending. Please upload payment proof in the school portal.',
            ],
            [
                'slug'          => 'email.fees.receipt_approved',
                'title'         => 'Your fee payment has been approved',
                'body_template' => 'Payment for {{context_title}} has been verified by {{sahodaya_name}}. Your official receipt (No. {{receipt_no}}) is attached to this email.',
            ],
            [
                'slug'          => 'email.admission_enquiry',
                'title'         => 'New Admission Enquiry',
                'body_template' => '',
            ],
            [
                'slug'          => 'email.auth.verify_email',
                'title'         => 'Verify your Gmail address',
                'body_template' => 'Your school {{school_name}} is registered with {{sahodaya_name}}. Please confirm your Gmail address to activate your school portal account.',
            ],
            [
                'slug'          => 'email.auth.reset_password',
                'title'         => 'Reset your password',
                'body_template' => 'We received a request to reset the password for your {{school_name}} portal account with {{sahodaya_name}}.',
            ],
        ];
    }
}
