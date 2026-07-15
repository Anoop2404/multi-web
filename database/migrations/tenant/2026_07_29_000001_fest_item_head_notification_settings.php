<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Head-first rebuild, Phase 3: per-Event-Head notification settings.
     *
     * A single JSON column rather than several new columns — this is admin-configured,
     * optional, sparse data (most heads will never touch it), with a shape that's still
     * evolving (disabled triggers today, may grow custom-message overrides later):
     *
     *  {
     *      "disabled_triggers": ["registration_approved", ...],   // omit/empty = everything enabled (today's behavior)
     *      "extra_recipient_user_ids": [12, 45]                    // existing platform users only, never free-text emails
     *  }
     *
     * Consumed by FestItemHead::notificationEnabledFor()/extraRecipientUserIds() and
     * FestEventNotifier's per-trigger gate + extra-recipient fan-out.
     */
    public function up(): void
    {
        if (Schema::hasTable('fest_item_heads') && ! Schema::hasColumn('fest_item_heads', 'notification_settings')) {
            Schema::table('fest_item_heads', function (Blueprint $table) {
                $table->json('notification_settings')->nullable()->after('discipline_event_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_item_heads') && Schema::hasColumn('fest_item_heads', 'notification_settings')) {
            Schema::table('fest_item_heads', function (Blueprint $table) {
                $table->dropColumn('notification_settings');
            });
        }
    }
};
