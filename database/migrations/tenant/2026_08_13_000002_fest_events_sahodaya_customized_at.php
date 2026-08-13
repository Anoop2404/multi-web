<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * STATE_SAHODAYA_RULE_BOUNDARY_FIX_PLAN — Set 1, Item 3
 *
 * Adds a nullable sahodaya_customized_at timestamp to fest_events.
 * Stamped by FestEventController::update() whenever a Sahodaya Admin
 * edits a state-seeded field (title, dates, venue, fee, description).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_events')) {
            return;
        }

        if (! Schema::hasColumn('fest_events', 'sahodaya_customized_at')) {
            Schema::table('fest_events', function (Blueprint $table) {
                $table->timestamp('sahodaya_customized_at')
                    ->nullable()
                    ->after('status')
                    ->comment('Set when a Sahodaya Admin edits a state-seeded field; drives customisation indicator badge.');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_events') && Schema::hasColumn('fest_events', 'sahodaya_customized_at')) {
            Schema::table('fest_events', function (Blueprint $table) {
                $table->dropColumn('sahodaya_customized_at');
            });
        }
    }
};
