<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fest_events', 'fee_settings')) {
            return;
        }

        // Runs in each Sahodaya database; also covers shared-database installations.
        DB::table('fest_events')->select('id', 'fee_settings')->chunkById(200, function ($events) {
            foreach ($events as $event) {
                $settings = json_decode($event->fee_settings ?? '{}', true) ?: [];
                $settings['require_fee_before_registration'] = false;

                DB::table('fest_events')->where('id', $event->id)->update([
                    'fee_settings' => json_encode($settings, JSON_THROW_ON_ERROR),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Previous per-event choices cannot be reconstructed safely.
    }
};
