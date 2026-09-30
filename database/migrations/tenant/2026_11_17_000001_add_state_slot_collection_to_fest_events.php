<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "State slot collection": before a hub's winners are registered with State, schools get a chance
 * to accept or opt out of the slot their rank earned (see FestStateSlotCollectionService). This is
 * a phase gate on the existing State nomination sheet, not a parallel data store — opening it
 * auto-fills the sheet's top ranks per item (FestStateWinnerSheetService::autoFill()) and marks
 * those selections as awaiting a school response (fest_state_nomination_selections.school_response,
 * see the sibling migration), and approving it locks further changes to the school-facing side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_events', function (Blueprint $table) {
            $table->boolean('state_slot_collection_open')->default(false)->after('state_program_id');
            $table->timestamp('state_slot_collection_opened_at')->nullable()->after('state_slot_collection_open');
            $table->timestamp('state_slot_collection_approved_at')->nullable()->after('state_slot_collection_opened_at');
            $table->unsignedBigInteger('state_slot_collection_approved_by')->nullable()->after('state_slot_collection_approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('fest_events', function (Blueprint $table) {
            $table->dropColumn([
                'state_slot_collection_open',
                'state_slot_collection_opened_at',
                'state_slot_collection_approved_at',
                'state_slot_collection_approved_by',
            ]);
        });
    }
};
