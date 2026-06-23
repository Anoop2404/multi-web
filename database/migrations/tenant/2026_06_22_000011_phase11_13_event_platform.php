<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fest_events', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
            $table->string('title');
            $table->enum('event_type', ['kalolsavam', 'sports', 'kids_fest', 'teacher_fest', 'custom'])->default('kalolsavam');
            $table->enum('conductor_level', ['state', 'sahodaya', 'school'])->default('sahodaya');
            $table->boolean('is_cascaded')->default(false);
            $table->unsignedBigInteger('parent_event_id')->nullable();
            $table->date('registration_open')->nullable();
            $table->date('registration_close')->nullable();
            $table->date('event_start')->nullable();
            $table->date('event_end')->nullable();
            $table->string('venue')->nullable();
            $table->enum('fee_type', ['none', 'flat_school', 'per_participant', 'per_item'])->default('none');
            $table->decimal('fee_amount', 10, 2)->nullable();
            $table->enum('status', ['draft', 'published', 'registration_open', 'ongoing', 'completed', 'cancelled'])->default('draft');
            $table->boolean('results_published')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'event_type', 'status']);
        });

        Schema::create('fest_event_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            $table->string('title');
            $table->enum('category', ['music', 'dance', 'drama', 'literary', 'sports', 'general'])->default('general');
            $table->enum('participant_type', ['individual', 'group', 'team'])->default('individual');
            $table->enum('gender', ['male', 'female', 'mixed', 'open'])->default('open');
            $table->enum('class_group', ['lp', 'up', 'hs', 'hss', 'open'])->default('open');
            $table->unsignedSmallInteger('max_per_school')->nullable();
            $table->unsignedSmallInteger('min_group_size')->nullable();
            $table->unsignedSmallInteger('max_group_size')->nullable();
            $table->unsignedSmallInteger('qualify_count')->nullable();
            $table->enum('owner_level', ['state', 'sahodaya', 'school'])->default('sahodaya');
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('fest_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->foreign('item_id')->references('id')->on('fest_event_items')->nullOnDelete();
            $table->string('school_id');
            $table->enum('mode', ['full', 'winner_only'])->default('full');
            $table->enum('status', ['draft', 'submitted', 'pending_approval', 'approved', 'rejected', 'withdrawn'])->default('draft');
            $table->unsignedBigInteger('fee_receipt_id')->nullable();
            $table->foreign('fee_receipt_id')->references('id')->on('fee_receipts')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'school_id']);
        });

        Schema::create('fest_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('registration_id');
            $table->foreign('registration_id')->references('id')->on('fest_registrations')->cascadeOnDelete();
            $table->string('team_name')->nullable();
            $table->enum('status', ['active', 'withdrawn'])->default('active');
            $table->timestamps();
        });

        Schema::create('fest_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('registration_id');
            $table->foreign('registration_id')->references('id')->on('fest_registrations')->cascadeOnDelete();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->foreign('group_id')->references('id')->on('fest_groups')->nullOnDelete();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->enum('participant_type', ['student', 'teacher'])->default('student');
            $table->unsignedSmallInteger('chest_no')->nullable();
            $table->timestamps();

            $table->index(['registration_id', 'student_id']);
        });

        Schema::create('fest_attendance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            $table->unsignedBigInteger('item_id');
            $table->foreign('item_id')->references('id')->on('fest_event_items')->cascadeOnDelete();
            $table->unsignedBigInteger('participant_id');
            $table->foreign('participant_id')->references('id')->on('fest_participants')->cascadeOnDelete();
            $table->enum('status', ['present', 'absent'])->default('present');
            $table->unsignedBigInteger('marked_by')->nullable();
            $table->timestamp('marked_at')->nullable();
            $table->unsignedBigInteger('corrected_by')->nullable();
            $table->timestamp('corrected_at')->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'participant_id']);
        });

        Schema::create('fest_marks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            $table->unsignedBigInteger('item_id');
            $table->foreign('item_id')->references('id')->on('fest_event_items')->cascadeOnDelete();
            $table->unsignedBigInteger('participant_id');
            $table->foreign('participant_id')->references('id')->on('fest_participants')->cascadeOnDelete();
            $table->enum('grade', ['A', 'B', 'C'])->nullable();
            $table->unsignedTinyInteger('position')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->json('ref_data_json')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'participant_id']);
        });

        Schema::create('fest_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->foreign('item_id')->references('id')->on('fest_event_items')->nullOnDelete();
            $table->string('school_id');
            $table->unsignedSmallInteger('total_points')->default(0);
            $table->unsignedSmallInteger('rank')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('published_by')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'school_id']);
        });

        Schema::create('fest_qualifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            $table->unsignedBigInteger('item_id');
            $table->foreign('item_id')->references('id')->on('fest_event_items')->cascadeOnDelete();
            $table->unsignedBigInteger('participant_id');
            $table->foreign('participant_id')->references('id')->on('fest_participants')->cascadeOnDelete();
            $table->unsignedBigInteger('next_level_event_id')->nullable();
            $table->timestamp('promoted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('event_type')->nullable();
            $table->enum('certificate_type', ['winner', 'participation', 'merit', 'completion'])->default('winner');
            $table->string('template_file_path')->nullable();
            $table->json('dynamic_fields_json')->nullable();
            $table->timestamps();
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('template_id')->nullable();
            $table->foreign('template_id')->references('id')->on('certificate_templates')->nullOnDelete();
            $table->uuid('verification_uuid')->unique();
            $table->string('file_path')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('screen_settings', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('slug')->unique();
            $table->string('title');
            $table->json('config_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screen_settings');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('certificate_templates');
        Schema::dropIfExists('fest_qualifications');
        Schema::dropIfExists('fest_results');
        Schema::dropIfExists('fest_marks');
        Schema::dropIfExists('fest_attendance');
        Schema::dropIfExists('fest_participants');
        Schema::dropIfExists('fest_groups');
        Schema::dropIfExists('fest_registrations');
        Schema::dropIfExists('fest_event_items');
        Schema::dropIfExists('fest_events');
    }
};
