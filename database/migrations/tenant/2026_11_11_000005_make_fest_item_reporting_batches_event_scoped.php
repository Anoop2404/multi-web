<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Batches are now a common roster per event (create "Batch 1" once, assign
     * registrations from ANY item into it) instead of a separate set per item.
     */
    public function up(): void
    {
        Schema::table('fest_item_reporting_batches', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            // SQLite refuses to drop a column a composite index still references (Postgres
            // drops the index along with it), which broke every test run's migrate step.
            $table->dropIndex(['item_id', 'sort_order']);
            $table->dropColumn('item_id');
        });
    }

    public function down(): void
    {
        Schema::table('fest_item_reporting_batches', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->after('event_id')->constrained('fest_event_items')->cascadeOnDelete();
        });
    }
};
