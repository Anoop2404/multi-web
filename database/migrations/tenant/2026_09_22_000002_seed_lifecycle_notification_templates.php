<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * LIFE-08/09/10 fix (functional audit, 2026-08-11/12): backfills the two new
 * school-facing notification template slugs into every existing tenant DB,
 * the same way 2026_06_22_000016_seed_notification_templates.php backfilled
 * the original fest.* slugs — NotificationTemplatesSeeder alone only runs on
 * fresh dev seeds/demo tenants, not automatically against already-provisioned
 * production tenants. The two *_admin slugs (fest.registration.needs_reapproval_admin,
 * fest.registration.submitted_admin) are intentionally NOT seeded here, matching
 * the existing fest.registration.withdrawn_admin precedent: admin-facing slugs
 * have no default text anywhere in this codebase and are opt-in via the
 * Notification Templates editor.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rows = [
            [
                'slug' => 'fest.results.unpublished',
                'title' => 'Event results unpublished',
                'body_template' => 'Results for {{event_title}} have been unpublished and are being revised. They will be re-published once corrected.',
            ],
            [
                'slug' => 'fest.registration.needs_reapproval',
                'title' => 'Registration needs re-approval',
                'body_template' => 'Your roster change for {{event_title}} ({{item_title}}) has sent this registration back for Sahodaya approval.',
            ],
        ];

        foreach ($rows as $row) {
            if (DB::table('notification_templates')->where('slug', $row['slug'])->exists()) {
                continue;
            }
            DB::table('notification_templates')->insert(array_merge($row, [
                'tenant_id'      => null,
                'channels_json'  => json_encode(['in_app']),
                'is_active'      => true,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]));
        }
    }

    public function down(): void
    {
        DB::table('notification_templates')->whereIn('slug', [
            'fest.results.unpublished',
            'fest.registration.needs_reapproval',
        ])->delete();
    }
};
