<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Region-sourced partition children (spawned by FestRegionPartitionService) previously
 * only carried a string partition_key slug (Str::slug($region->code ?: $region->name))
 * with no real foreign key back to the regions table. This adds one, and backfills
 * existing partitions by re-deriving that same slug match — the only mapping that has
 * ever existed for these rows.
 *
 * See docs/REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md §2.2 / Phase 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_events') && ! Schema::hasColumn('fest_events', 'region_id')) {
            Schema::table('fest_events', function (Blueprint $table) {
                $table->foreignId('region_id')->nullable()->after('partition_key')->constrained('regions')->nullOnDelete();
            });
        }

        if (Schema::hasTable('fest_events') && Schema::hasTable('regions')) {
            $this->backfill();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_events') && Schema::hasColumn('fest_events', 'region_id')) {
            Schema::table('fest_events', function (Blueprint $table) {
                $table->dropForeign(['region_id']);
                $table->dropColumn('region_id');
            });
        }
    }

    /**
     * Re-derive the slug match FestRegionPartitionService::partitionKeyForRegion() has
     * always used (Str::slug($region->code ?: $region->name)), scoped per tenant so two
     * Sahodayas can't collide on the same slug.
     */
    private function backfill(): void
    {
        $partitions = DB::table('fest_events')
            ->whereNotNull('partition_key')
            ->whereNull('region_id')
            ->where('partition_role', 'region')
            ->get(['id', 'tenant_id', 'partition_key']);

        if ($partitions->isEmpty()) {
            return;
        }

        $regionsByTenant = DB::table('regions')
            ->get(['id', 'tenant_id', 'code', 'name'])
            ->groupBy('tenant_id');

        foreach ($partitions as $partition) {
            $regions = $regionsByTenant->get($partition->tenant_id, collect());

            $match = $regions->first(function ($region) use ($partition) {
                return Str::slug($region->code ?: $region->name) === $partition->partition_key;
            });

            if ($match) {
                DB::table('fest_events')->where('id', $partition->id)->update(['region_id' => $match->id]);
            }
        }
    }
};
