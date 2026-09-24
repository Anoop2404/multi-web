<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_item_reporting_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('fest_events')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('fest_event_items')->cascadeOnDelete();
            $table->string('label');
            $table->dateTime('report_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['item_id', 'sort_order']);
        });

        Schema::table('fest_registrations', function (Blueprint $table) {
            $table->foreignId('reporting_batch_id')->nullable()->after('item_id')->constrained('fest_item_reporting_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fest_registrations', function (Blueprint $table) {
            $table->dropForeign(['reporting_batch_id']);
            $table->dropColumn('reporting_batch_id');
        });

        Schema::dropIfExists('fest_item_reporting_batches');
    }
};
