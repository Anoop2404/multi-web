<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable fee_customized_at timestamp to fest_events, mirroring
 * sahodaya_customized_at (2026_08_13_000002_fest_events_sahodaya_customized_at.php)
 * but for the hub -> partition-child boundary: stamped by
 * FestEventSettingsController::updateFeeSettings()/updateItemFee() and
 * FestItemHeadController::updateWindows() whenever a region/finale child's own fee
 * data is edited directly, so FestSchoolEventFeeService::propagateFeeSettingsToChildren()
 * can leave that child's fees alone on the hub's next save instead of reverting them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_events')) {
            return;
        }

        if (! Schema::hasColumn('fest_events', 'fee_customized_at')) {
            Schema::table('fest_events', function (Blueprint $table) {
                $table->timestamp('fee_customized_at')
                    ->nullable()
                    ->after('sahodaya_customized_at')
                    ->comment('Set when a Sahodaya Admin edits a partition child event\'s own fee data directly; protects it from the hub fee cascade.');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_events') && Schema::hasColumn('fest_events', 'fee_customized_at')) {
            Schema::table('fest_events', function (Blueprint $table) {
                $table->dropColumn('fee_customized_at');
            });
        }
    }
};
