<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_state_program_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('state_program_id');
            $table->foreign('state_program_id')->references('id')->on('fest_state_programs')->cascadeOnDelete();
            $table->string('title');
            $table->string('item_code', 20)->nullable();
            $table->enum('category', ['music', 'dance', 'drama', 'literary', 'sports', 'general'])->default('general');
            $table->string('stage_type', 20)->nullable();
            $table->string('venue_type', 20)->nullable();
            $table->string('competition_format', 30)->nullable();
            $table->string('sport_discipline', 40)->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->json('criteria_json')->nullable();
            $table->enum('participant_type', ['individual', 'group', 'team'])->default('individual');
            $table->enum('gender', ['male', 'female', 'mixed', 'open'])->default('open');
            $table->enum('class_group', ['lp', 'up', 'hs', 'hss', 'open'])->default('open');
            $table->unsignedSmallInteger('max_per_school')->nullable();
            $table->unsignedSmallInteger('min_group_size')->nullable();
            $table->unsignedSmallInteger('max_group_size')->nullable();
            $table->unsignedSmallInteger('qualify_count')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_state_program_items');
    }
};
