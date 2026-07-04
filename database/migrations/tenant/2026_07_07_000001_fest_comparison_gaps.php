<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_event_items') && ! Schema::hasColumn('fest_event_items', 'is_mandatory')) {
            Schema::table('fest_event_items', function (Blueprint $table) {
                $table->boolean('is_mandatory')->default(false)->after('is_enabled');
            });
        }

        if (Schema::hasTable('fest_catalog_items') && ! Schema::hasColumn('fest_catalog_items', 'is_mandatory')) {
            Schema::table('fest_catalog_items', function (Blueprint $table) {
                $table->boolean('is_mandatory')->default(false)->after('is_enabled');
            });
        }

        Schema::table('fest_events', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_events', 'verification_day')) {
                $table->date('verification_day')->nullable()->after('event_end');
            }
            if (! Schema::hasColumn('fest_events', 'manual_pdf_path')) {
                $table->string('manual_pdf_path')->nullable()->after('banner_path');
            }
        });

        if (! Schema::hasTable('fest_substitution_requests')) {
            Schema::create('fest_substitution_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id');
                $table->uuid('school_id');
                $table->unsignedBigInteger('registration_id');
                $table->unsignedBigInteger('original_participant_id');
                $table->unsignedBigInteger('replacement_participant_id')->nullable();
                $table->unsignedBigInteger('replacement_student_id')->nullable();
                $table->text('reason');
                $table->string('status', 20)->default('pending');
                $table->text('resolution_note')->nullable();
                $table->unsignedBigInteger('requested_by_user_id')->nullable();
                $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['event_id', 'school_id', 'status']);
            });
        }

        if (! Schema::hasTable('fest_clash_requests')) {
            Schema::create('fest_clash_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id');
                $table->uuid('school_id');
                $table->unsignedBigInteger('participant_id');
                $table->unsignedBigInteger('schedule_id_a')->nullable();
                $table->unsignedBigInteger('schedule_id_b')->nullable();
                $table->text('description')->nullable();
                $table->text('requested_resolution')->nullable();
                $table->string('status', 20)->default('pending');
                $table->text('resolution_note')->nullable();
                $table->unsignedBigInteger('requested_by_user_id')->nullable();
                $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['event_id', 'status']);
            });
        }

        if (! Schema::hasTable('fest_school_verifications')) {
            Schema::create('fest_school_verifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id');
                $table->uuid('school_id');
                $table->boolean('documents_verified')->default(false);
                $table->unsignedBigInteger('verified_by_user_id')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['event_id', 'school_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_school_verifications');
        Schema::dropIfExists('fest_clash_requests');
        Schema::dropIfExists('fest_substitution_requests');

        Schema::table('fest_events', function (Blueprint $table) {
            if (Schema::hasColumn('fest_events', 'verification_day')) {
                $table->dropColumn('verification_day');
            }
            if (Schema::hasColumn('fest_events', 'manual_pdf_path')) {
                $table->dropColumn('manual_pdf_path');
            }
        });

        if (Schema::hasColumn('fest_event_items', 'is_mandatory')) {
            Schema::table('fest_event_items', fn (Blueprint $table) => $table->dropColumn('is_mandatory'));
        }
        if (Schema::hasColumn('fest_catalog_items', 'is_mandatory')) {
            Schema::table('fest_catalog_items', fn (Blueprint $table) => $table->dropColumn('is_mandatory'));
        }
    }
};
