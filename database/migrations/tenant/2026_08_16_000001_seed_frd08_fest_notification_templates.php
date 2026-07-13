<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        $now = now();
        $rows = [
            [
                'slug' => 'fest.registration.open',
                'title' => 'Event registration open',
                'body_template' => 'Registration is now open for {{event_title}} ({{competition_label}}). Closes {{close_date}}.',
            ],
            [
                'slug' => 'fest.payment.pending',
                'title' => 'Event fee payment pending',
                'body_template' => 'Payment of ₹{{amount}} is still pending for {{event_title}}. Please upload fee proof from the school portal.',
            ],
            [
                'slug' => 'fest.competition.reminder',
                'title' => 'Competition reminder',
                'body_template' => 'Reminder: {{event_title}} starts on {{start_date}}. Venue: {{venue}}.',
            ],
            [
                'slug' => 'fest.certificate.available',
                'title' => 'Event certificates available',
                'body_template' => '{{count}} certificate(s) for {{event_title}} are now available to download.',
            ],
        ];

        foreach ($rows as $row) {
            if (DB::table('notification_templates')->where('slug', $row['slug'])->exists()) {
                continue;
            }

            DB::table('notification_templates')->insert(array_merge($row, [
                'tenant_id' => null,
                'channels_json' => json_encode(['in_app', 'email']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        DB::table('notification_templates')->whereIn('slug', [
            'fest.registration.open',
            'fest.payment.pending',
            'fest.competition.reminder',
            'fest.certificate.available',
        ])->delete();
    }
};
