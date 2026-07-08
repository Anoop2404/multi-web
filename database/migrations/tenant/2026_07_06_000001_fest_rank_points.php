<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_rank_points')) {
            return;
        }

        Schema::create('fest_rank_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedTinyInteger('rank');
            $table->unsignedSmallInteger('points');
            $table->boolean('is_group')->default(false);
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            $table->unique(['event_id', 'rank', 'is_group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_rank_points');
    }
};
