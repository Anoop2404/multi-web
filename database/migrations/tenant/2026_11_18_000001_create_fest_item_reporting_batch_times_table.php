<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_item_reporting_batch_times', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('fest_events')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('fest_event_items')->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('fest_item_reporting_batches')->cascadeOnDelete();
            $table->dateTime('report_at')->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'batch_id']);
            $table->index(['event_id', 'item_id']);
        });

        // Backfill any existing batch report times to the items that actually have registrations in that batch
        $batches = DB::table('fest_item_reporting_batches')->whereNotNull('report_at')->get();
        foreach ($batches as $batch) {
            $itemIds = DB::table('fest_registrations')
                ->where('reporting_batch_id', $batch->id)
                ->distinct()
                ->pluck('item_id');

            foreach ($itemIds as $itemId) {
                DB::table('fest_item_reporting_batch_times')->insertOrIgnore([
                    'event_id'   => $batch->event_id,
                    'item_id'    => $itemId,
                    'batch_id'   => $batch->id,
                    'report_at'  => $batch->report_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_item_reporting_batch_times');
    }
};
