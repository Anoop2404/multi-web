<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a Sahodaya mark two or more items (e.g. "STEM — Science, Category I" and
 * "STEM — Maths, Category I") as mutually-exclusive alternatives by giving them the
 * same exclusive_group_key: a school may hold an active registration on at most one
 * item per key at a time, but is free to switch after withdrawing. Enforced in
 * FestParticipationLimitService::validateRegistration().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_event_items')) {
            return;
        }

        Schema::table('fest_event_items', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_event_items', 'exclusive_group_key')) {
                $table->string('exclusive_group_key', 64)->nullable()->after('item_code');
                $table->index('exclusive_group_key');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fest_event_items')) {
            return;
        }

        Schema::table('fest_event_items', function (Blueprint $table) {
            if (Schema::hasColumn('fest_event_items', 'exclusive_group_key')) {
                $table->dropIndex(['exclusive_group_key']);
                $table->dropColumn('exclusive_group_key');
            }
        });
    }
};
