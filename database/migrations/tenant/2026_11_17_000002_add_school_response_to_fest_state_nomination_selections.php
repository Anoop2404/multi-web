<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A school's own response to being auto-filled into a State winner slot (see
 * FestStateSlotCollectionService). Null on any selection made outside that flow — a Sahodaya can
 * still hand-pick a winner sheet the old way, and those rows never wait on a school. Values once
 * set: 'pending' (offered, no response yet), 'accepted', 'opted_out' (the row's own `status` is
 * also set to the existing `declined` and `skip_reason` carries the school's reason, so the sheet
 * and the certified-batch payload keep reading one place for "not going").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_state_nomination_selections', function (Blueprint $table) {
            $table->string('school_response', 20)->nullable()->after('status');
            $table->unsignedBigInteger('school_responded_by')->nullable()->after('school_response');
            $table->string('school_responded_by_name', 150)->nullable()->after('school_responded_by');
            $table->timestamp('school_responded_at')->nullable()->after('school_responded_by_name');
            // The selection this one filled in for after an opt-out freed its slot — an audit trail
            // ("rank 3 replaced rank 1 after rank 1 opted out"), not something anything reads back.
            $table->unsignedBigInteger('replaces_selection_id')->nullable()->after('school_responded_at');
            $table->index(['school_id', 'school_response']);
        });
    }

    public function down(): void
    {
        Schema::table('fest_state_nomination_selections', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'school_response']);
            $table->dropColumn([
                'school_response',
                'school_responded_by',
                'school_responded_by_name',
                'school_responded_at',
                'replaces_selection_id',
            ]);
        });
    }
};
