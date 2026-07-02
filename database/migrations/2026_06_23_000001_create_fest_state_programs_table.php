<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_state_programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->enum('event_type', ['kalolsavam', 'sports', 'kids_fest', 'teacher_fest', 'custom'])->default('kalolsavam');
            $table->json('conduct_levels')->nullable();
            $table->string('academic_year')->nullable();
            $table->date('registration_open')->nullable();
            $table->date('registration_close')->nullable();
            $table->date('event_start')->nullable();
            $table->date('event_end')->nullable();
            $table->string('venue')->nullable();
            $table->enum('fee_type', ['none', 'flat_school', 'per_participant', 'per_item'])->default('none');
            $table->decimal('fee_amount', 10, 2)->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('fest_state_program_propagations', function (Blueprint $table) {
            $table->id();
            $table->uuid('state_program_id');
            $table->foreign('state_program_id')->references('id')->on('fest_state_programs')->cascadeOnDelete();
            $table->string('sahodaya_id');
            $table->foreign('sahodaya_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_event_id')->nullable();
            $table->enum('level_round', ['state', 'sahodaya', 'school'])->default('sahodaya');
            $table->timestamps();

            $table->unique(['state_program_id', 'sahodaya_id', 'level_round']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_state_program_propagations');
        Schema::dropIfExists('fest_state_programs');
    }
};
