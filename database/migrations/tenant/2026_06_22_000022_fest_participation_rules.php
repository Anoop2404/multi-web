<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_participation_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            $table->string('class_group', 20)->nullable();
            $table->unsignedSmallInteger('max_total_events')->nullable();
            $table->unsignedSmallInteger('max_onstage')->nullable();
            $table->unsignedSmallInteger('max_offstage')->nullable();
            $table->unsignedSmallInteger('max_group_events')->nullable();
            $table->unsignedSmallInteger('max_individual_events')->nullable();
            $table->unsignedSmallInteger('max_events_per_student')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['event_id', 'class_group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_participation_rules');
    }
};
