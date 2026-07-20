<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many judges independently mark this item on paper. When > 1, the
 * printed mark-entry sheet produces one blank sheet per judge plus a
 * consolidated "Sum Sheet", and online mark entry shows one input column
 * per judge (that judge's paper subtotal) instead of a single score field.
 * Null/1 keeps the legacy single-score behaviour unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_event_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('mark_judge_count')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fest_event_items', function (Blueprint $table) {
            $table->dropColumn('mark_judge_count');
        });
    }
};
