<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * State-domain operational tables (run on State tenant database).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('state_qualifier_intakes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('state_program_id');
            $table->string('source_tenant_id');
            $table->unsignedBigInteger('source_event_id');
            $table->string('idempotency_key', 128)->unique();
            $table->string('status', 20)->default('received');
            $table->json('payload');
            $table->string('payload_hash', 64)->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['state_program_id', 'status']);
            $table->index(['source_tenant_id', 'source_event_id']);
        });

        Schema::create('state_qualifier_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('intake_id');
            $table->foreign('intake_id')->references('id')->on('state_qualifier_intakes')->cascadeOnDelete();
            $table->string('source_registration_id')->nullable();
            $table->string('source_participant_id')->nullable();
            $table->string('school_id');
            $table->string('school_name')->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('item_code', 64)->nullable();
            $table->string('item_name')->nullable();
            $table->string('student_name');
            $table->string('class_name')->nullable();
            $table->unsignedTinyInteger('position')->nullable();
            $table->string('grade', 8)->nullable();
            $table->unsignedSmallInteger('points')->default(0);
            $table->string('partition_key', 64)->nullable();
            $table->string('qualifier_type', 32)->default('regional_winner');
            $table->string('status', 20)->default('pending');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['intake_id', 'status']);
            $table->index(['item_code', 'school_id']);
        });

        Schema::create('state_fest_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('state_program_id');
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('status', 20)->default('draft');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['state_program_id', 'status']);
        });

        Schema::create('state_fest_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('state_event_id');
            $table->foreign('state_event_id')->references('id')->on('state_fest_events')->cascadeOnDelete();
            $table->unsignedBigInteger('qualifier_entry_id')->nullable();
            $table->string('school_id');
            $table->string('school_name')->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('item_code', 64)->nullable();
            $table->string('status', 20)->default('draft');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['state_event_id', 'status']);
        });

        Schema::create('state_fest_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('registration_id');
            $table->foreign('registration_id')->references('id')->on('state_fest_registrations')->cascadeOnDelete();
            $table->string('student_name');
            $table->string('class_name')->nullable();
            $table->string('chest_number', 32)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('state_fest_marks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('state_event_id');
            $table->foreign('state_event_id')->references('id')->on('state_fest_events')->cascadeOnDelete();
            $table->unsignedBigInteger('registration_id');
            $table->unsignedBigInteger('participant_id')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->string('grade', 8)->nullable();
            $table->unsignedTinyInteger('position')->nullable();
            $table->unsignedSmallInteger('points')->default(0);
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('entered_by')->nullable();
            $table->timestamps();

            $table->index(['state_event_id', 'registration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_fest_marks');
        Schema::dropIfExists('state_fest_participants');
        Schema::dropIfExists('state_fest_registrations');
        Schema::dropIfExists('state_fest_events');
        Schema::dropIfExists('state_qualifier_entries');
        Schema::dropIfExists('state_qualifier_intakes');
    }
};
