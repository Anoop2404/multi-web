<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a region be scoped to a single FestEvent's Phases instead of Sahodaya-wide, for
 * MCS-style events that want a new region visible only for one phase's picker (e.g.
 * Sargadhara) without showing up in Membership -> Regions, Rounds & Levels, the school's
 * annual region dropdown, or any other tenant-wide consumer. See docs/MCS_FOUR_PHASE_COMPLETION_PLAN.md.
 *
 * Null fest_event_id (the default, and every existing row) means "global" -- unchanged
 * behavior. A non-null value scopes the region to that event only; App\Models\Region's
 * globalOnly()/visibleToEvent() scopes are how call sites opt into the distinction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->unsignedBigInteger('fest_event_id')->nullable()->after('tenant_id');
            $table->foreign('fest_event_id')->references('id')->on('fest_events')->nullOnDelete();
            $table->index('fest_event_id');
        });

        Schema::table('regions', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'code']);
            $table->unique(['tenant_id', 'fest_event_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'fest_event_id', 'code']);
            $table->dropForeign(['fest_event_id']);
            $table->dropIndex(['fest_event_id']);
            $table->dropColumn('fest_event_id');
        });

        Schema::table('regions', function (Blueprint $table) {
            $table->unique(['tenant_id', 'code']);
        });
    }
};
