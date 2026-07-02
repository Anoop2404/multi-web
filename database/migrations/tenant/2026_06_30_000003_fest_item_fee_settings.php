<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_events', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_events', 'fee_settings')) {
                $table->json('fee_settings')->nullable()->after('fee_amount');
            }
        });

        Schema::table('fest_event_items', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_event_items', 'fee_amount')) {
                $table->decimal('fee_amount', 10, 2)->nullable()->after('display_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fest_events', function (Blueprint $table) {
            if (Schema::hasColumn('fest_events', 'fee_settings')) {
                $table->dropColumn('fee_settings');
            }
        });

        Schema::table('fest_event_items', function (Blueprint $table) {
            if (Schema::hasColumn('fest_event_items', 'fee_amount')) {
                $table->dropColumn('fee_amount');
            }
        });
    }
};
