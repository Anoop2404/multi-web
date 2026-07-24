<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_event_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('fest_events')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 64)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['event_id', 'sort_order']);
        });

        Schema::table('fest_event_items', function (Blueprint $table) {
            $table->foreignId('phase_id')->nullable()->after('event_id')->constrained('fest_event_phases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fest_event_items', function (Blueprint $table) {
            $table->dropForeign(['phase_id']);
            $table->dropColumn('phase_id');
        });

        Schema::dropIfExists('fest_event_phases');
    }
};
