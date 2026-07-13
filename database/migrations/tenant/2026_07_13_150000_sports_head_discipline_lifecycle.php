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
                if (! Schema::hasColumn('fest_item_heads', 'status')) {
                    $table->string('status', 32)->default('draft')->after('approval_policy');
                }
                if (! Schema::hasColumn('fest_item_heads', 'venue')) {
                    $table->string('venue')->nullable()->after('status');
                }
                if (! Schema::hasColumn('fest_item_heads', 'event_start')) {
                    $table->date('event_start')->nullable()->after('venue');
                }
                if (! Schema::hasColumn('fest_item_heads', 'event_end')) {
                    $table->date('event_end')->nullable()->after('event_start');
                }
                if (! Schema::hasColumn('fest_item_heads', 'discipline_event_id')) {
                    $table->unsignedBigInteger('discipline_event_id')->nullable()->after('event_id');
                    $table->index('discipline_event_id');
                }
            });
        }

        if (Schema::hasTable('fest_events')) {
            Schema::table('fest_events', function (Blueprint $table) {
                if (! Schema::hasColumn('fest_events', 'sport_discipline')) {
                    $table->string('sport_discipline', 60)->nullable()->after('event_type');
                }
                if (! Schema::hasColumn('fest_events', 'source_head_id')) {
                    $table->unsignedBigInteger('source_head_id')->nullable()->after('sport_discipline');
                    $table->index('source_head_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_item_heads')) {
            Schema::table('fest_item_heads', function (Blueprint $table) {
                foreach (['status', 'venue', 'event_start', 'event_end', 'discipline_event_id'] as $col) {
                    if (Schema::hasColumn('fest_item_heads', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('fest_events')) {
            Schema::table('fest_events', function (Blueprint $table) {
                foreach (['sport_discipline', 'source_head_id'] as $col) {
                    if (Schema::hasColumn('fest_events', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
