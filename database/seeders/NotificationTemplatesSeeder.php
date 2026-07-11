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
        ];
    }
}
