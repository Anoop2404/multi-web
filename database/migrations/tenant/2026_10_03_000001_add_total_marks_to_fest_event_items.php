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
            if (! Schema::hasColumn('fest_event_items', 'total_marks')) {
                $table->decimal('total_marks', 8, 2)->nullable()->after('result_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fest_event_items', function (Blueprint $table) {
            if (Schema::hasColumn('fest_event_items', 'total_marks')) {
                $table->dropColumn('total_marks');
            }
        });
    }
};
