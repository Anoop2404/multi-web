<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_events', function (Blueprint $table) {
            // Null = use the platform default (15) — see FestItemReportingBatchController::
            // DEFAULT_MIN_REGISTRATIONS_FOR_BATCHING. A Sahodaya can override it per event.
            $table->unsignedInteger('reporting_batch_min_registrations')->nullable()->after('phase_mode_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('fest_events', function (Blueprint $table) {
            $table->dropColumn('reporting_batch_min_registrations');
        });
    }
};
