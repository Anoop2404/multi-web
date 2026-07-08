<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_item_heads')) {
            Schema::table('fest_item_heads', function (Blueprint $table) {
                // How items under this head are conducted:
                //  - 'same_time'      : all items run together on one date+time
                //  - 'different_days' : items are scheduled individually (per-item date/time)
                if (! Schema::hasColumn('fest_item_heads', 'schedule_mode')) {
                    $table->string('schedule_mode', 20)->default('different_days')->after('competition_end');
                }
                // Time-of-day used when schedule_mode = same_time (applies to all items).
                if (! Schema::hasColumn('fest_item_heads', 'competition_time')) {
                    $table->time('competition_time')->nullable()->after('schedule_mode');
                }
            });
        }

        if (Schema::hasTable('fest_event_items')) {
            Schema::table('fest_event_items', function (Blueprint $table) {
                // Per-item time-of-day (used in different_days mode, or inherited from head).
                if (! Schema::hasColumn('fest_event_items', 'competition_time')) {
                    $table->time('competition_time')->nullable()->after('competition_end');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_item_heads')) {
            Schema::table('fest_item_heads', function (Blueprint $table) {
                foreach (['schedule_mode', 'competition_time'] as $col) {
                    if (Schema::hasColumn('fest_item_heads', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('fest_event_items')) {
            Schema::table('fest_event_items', function (Blueprint $table) {
                if (Schema::hasColumn('fest_event_items', 'competition_time')) {
                    $table->dropColumn('competition_time');
                }
            });
        }
    }
};
