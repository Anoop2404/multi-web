<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $rows = [
            ['slug' => 'fest.registration.approved', 'title' => 'Event registration approved', 'body_template' => 'Your registration for {{event_title}} ({{item_title}}) has been approved.'],
            ['slug' => 'fest.registration.rejected', 'title' => 'Event registration rejected', 'body_template' => 'Your registration for {{event_title}} was not approved.'],
            ['slug' => 'fest.results.published', 'title' => 'Event results published', 'body_template' => 'Results for {{event_title}} are now published.'],
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
            'fest.registration.approved',
            'fest.registration.rejected',
            'fest.results.published',
        ])->delete();
    }
};
