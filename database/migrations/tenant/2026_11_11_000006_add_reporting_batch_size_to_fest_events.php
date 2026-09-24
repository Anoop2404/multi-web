<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_events', function (Blueprint $table) {
            // Null = use the platform default (8) -- see FestItemReportingBatchController::
            // DEFAULT_BATCH_SIZE. Drives "Auto-assign by distance": how many registrations
            // (closest schools first) go into each batch.
            $table->unsignedInteger('reporting_batch_size')->nullable()->after('reporting_batch_min_registrations');
        });
    }

    public function down(): void
    {
        Schema::table('fest_events', function (Blueprint $table) {
            $table->dropColumn('reporting_batch_size');
        });
    }
};
