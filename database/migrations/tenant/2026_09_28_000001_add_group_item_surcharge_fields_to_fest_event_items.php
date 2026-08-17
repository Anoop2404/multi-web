<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase L — per-participant group-item surcharge (item-level override).
 *
 * Group/team items currently bill a single static team/group amount per entry
 * regardless of team size (see FestItemFeeResolver::amountForItem() and
 * FestSportsCompositeFeeService's team-fee branch). These two nullable columns let a
 * specific item override that with `group_item_flat_fee + group_item_per_participant_rate
 * x actual participant count`, mirroring the existing `fee_amount` per-item override
 * column (already present on this table) -- item-level wins over the event-wide default,
 * which lives in `fest_events.fee_settings` (JSON, no schema change needed there).
 *
 * See docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md Section 7.4 (Phase L).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_event_items')) {
            return;
        }

        Schema::table('fest_event_items', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_event_items', 'group_item_flat_fee')) {
                $table->decimal('group_item_flat_fee', 10, 2)->nullable()->after('fee_amount');
            }
            if (! Schema::hasColumn('fest_event_items', 'group_item_per_participant_rate')) {
                $table->decimal('group_item_per_participant_rate', 10, 2)->nullable()->after('group_item_flat_fee');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fest_event_items')) {
            return;
        }

        Schema::table('fest_event_items', function (Blueprint $table) {
            foreach (['group_item_flat_fee', 'group_item_per_participant_rate'] as $col) {
                if (Schema::hasColumn('fest_event_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
