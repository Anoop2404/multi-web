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

        if (DB::table('notification_templates')->where('slug', 'fee.receipt.reversed')->exists()) {
            return;
        }

        $now = now();
        DB::table('notification_templates')->insert([
            'tenant_id'     => null,
            'slug'          => 'fee.receipt.reversed',
            'title'         => 'Fee receipt reversed',
            'body_template' => 'Fee receipt {{receipt_number}} for ₹{{amount}} has been reversed. Reason: {{reason}}',
            'channels_json' => json_encode(['in_app', 'email']),
            'is_active'     => true,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('notification_templates')) {
            DB::table('notification_templates')->where('slug', 'fee.receipt.reversed')->delete();
        }
    }
};
