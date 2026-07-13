<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FRD-08 Phase 5: Tie-break mode on items + lot-draw audit log.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_event_items')) {
            Schema::table('fest_event_items', function (Blueprint $table) {
                if (! Schema::hasColumn('fest_event_items', 'tiebreak_mode')) {
                    $table->string('tiebreak_mode', 30)->nullable()->after('qualify_count');
                }
                if (! Schema::hasColumn('fest_event_items', 'tiebreak_secondary')) {
                    $table->string('tiebreak_secondary', 40)->nullable()->after('tiebreak_mode');
                }
            });
        }

        if (! Schema::hasTable('fest_qualification_lot_draws')) {
            Schema::create('fest_qualification_lot_draws', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id');
                $table->unsignedBigInteger('item_id');
                $table->unsignedBigInteger('from_event_id')->nullable();
                $table->unsignedInteger('cutoff_position')->nullable();
                $table->json('contested_participant_ids')->nullable();
                $table->json('selected_participant_ids')->nullable();
                $table->string('method', 30)->default('auto_random');
                $table->string('seed')->nullable();
                $table->unsignedBigInteger('drawn_by')->nullable();
                $table->timestamp('drawn_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['event_id', 'item_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_qualification_lot_draws');

        if (Schema::hasTable('fest_event_items')) {
            Schema::table('fest_event_items', function (Blueprint $table) {
                if (Schema::hasColumn('fest_event_items', 'tiebreak_secondary')) {
                    $table->dropColumn('tiebreak_secondary');
                }
                if (Schema::hasColumn('fest_event_items', 'tiebreak_mode')) {
                    $table->dropColumn('tiebreak_mode');
                }
            });
        }
    }
};
