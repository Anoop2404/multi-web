<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_event_items')) {
            return;
        }

        Schema::table('fest_event_items', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_event_items', 'chest_no_start')) {
                $table->unsignedInteger('chest_no_start')->nullable()->after('item_reg_id_start');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fest_event_items')) {
            return;
        }

        Schema::table('fest_event_items', function (Blueprint $table) {
            if (Schema::hasColumn('fest_event_items', 'chest_no_start')) {
                $table->dropColumn('chest_no_start');
            }
        });
    }
};
