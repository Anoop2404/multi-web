<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_events')) {
            DB::table('fest_events')
                ->whereIn('event_type', ['kalotsav', 'art_fest', 'co_curricular'])
                ->update(['event_type' => 'kalolsavam']);

            DB::table('fest_events')
                ->whereIn('event_type', ['sports_meet', 'athletics'])
                ->update(['event_type' => 'sports']);
        }

        if (Schema::hasTable('fest_catalog_items')) {
            DB::table('fest_catalog_items')
                ->whereIn('event_type', ['kalotsav', 'art_fest', 'co_curricular'])
                ->update(['event_type' => 'kalolsavam']);

            DB::table('fest_catalog_items')
                ->whereIn('event_type', ['sports_meet', 'athletics'])
                ->update(['event_type' => 'sports']);
        }
    }

    public function down(): void
    {
        // No-op
    }
};
