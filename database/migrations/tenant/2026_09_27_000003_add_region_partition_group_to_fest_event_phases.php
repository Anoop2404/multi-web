<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md §7.3 item 2 (dated 2026-08-15).
 *
 * Marks a phase as "regional" and which region-group namespace it reads from. Set on
 * a phase like "Off Stage" (region_partition_group = 'off_stage') or "Sargadhara"
 * (region_partition_group = 'sargadhara'); left NULL on non-regional phases (e.g.
 * "Digi Fest", "Common Items") — exactly today's behavior for every phase, since a
 * NULL value here means "not a regional phase" and nothing downstream (
 * FestRegionPartitionService::schoolRegion()/syncPartitionsFromRegionsForPhase(),
 * FestItemSyncService::copyItemsToPartition()) treats a phase differently unless this
 * column is explicitly set. Nullable, no default, no backfill — every existing phase
 * row keeps region_partition_group = NULL after this migration runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_event_phases')) {
            return;
        }

        if (! Schema::hasColumn('fest_event_phases', 'region_partition_group')) {
            Schema::table('fest_event_phases', function (Blueprint $table) {
                $table->string('region_partition_group')->nullable()->after('code');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fest_event_phases')) {
            return;
        }

        if (Schema::hasColumn('fest_event_phases', 'region_partition_group')) {
            Schema::table('fest_event_phases', function (Blueprint $table) {
                $table->dropColumn('region_partition_group');
            });
        }
    }
};
