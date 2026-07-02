<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sahodaya_registration_windows')) {
            Schema::table('sahodaya_registration_windows', function (Blueprint $table) {
                foreach (['add_open', 'add_close', 'edit_open', 'edit_close'] as $col) {
                    if (! Schema::hasColumn('sahodaya_registration_windows', $col)) {
                        $table->dateTime($col)->nullable();
                    }
                }
            });
        }

        if (! Schema::hasTable('school_lock_overrides')) {
            Schema::create('school_lock_overrides', function (Blueprint $table) {
                $table->id();
                $table->string('sahodaya_id');
                $table->string('school_id');
                $table->enum('override_type', [
                    'unlock_add', 'unlock_edit', 'lock_add', 'lock_edit', 'unlock_all', 'lock_all',
                ]);
                $table->text('reason')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->timestamps();

                $table->index(['school_id', 'expires_at']);
                $table->index(['sahodaya_id', 'school_id']);
            });
        }

        if (Schema::hasTable('student_edit_change_requests')) {
            Schema::table('student_edit_change_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('student_edit_change_requests', 'school_approval_status')) {
                    $table->enum('school_approval_status', [
                        'pending_school', 'school_approved', 'school_rejected', 'bypassed',
                    ])->default('pending_school')->after('status');
                }
                if (! Schema::hasColumn('student_edit_change_requests', 'school_approved_by')) {
                    $table->unsignedBigInteger('school_approved_by')->nullable()->after('school_approval_status');
                }
                if (! Schema::hasColumn('student_edit_change_requests', 'school_approved_at')) {
                    $table->timestamp('school_approved_at')->nullable()->after('school_approved_by');
                }
                if (! Schema::hasColumn('student_edit_change_requests', 'school_rejection_note')) {
                    $table->text('school_rejection_note')->nullable()->after('school_approved_at');
                }
                if (! Schema::hasColumn('student_edit_change_requests', 'submitted_by_role')) {
                    $table->string('submitted_by_role')->nullable()->after('school_rejection_note');
                }
                if (! Schema::hasColumn('student_edit_change_requests', 'escalation_type')) {
                    $table->enum('escalation_type', ['direct_to_sahodaya', 'via_school_principal'])
                        ->default('via_school_principal')->after('submitted_by_role');
                }
            });
        }

        if (! Schema::hasTable('user_profile_change_requests')) {
            Schema::create('user_profile_change_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('school_id')->nullable();
                $table->json('changes_json');
                $table->text('reason')->nullable();
                $table->enum('status', [
                    'pending_school', 'school_approved', 'school_rejected',
                    'sahodaya_pending', 'approved', 'rejected',
                ])->default('pending_school');
                $table->enum('school_approval_status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->unsignedBigInteger('school_approved_by')->nullable();
                $table->timestamp('school_approved_at')->nullable();
                $table->unsignedBigInteger('sahodaya_approved_by')->nullable();
                $table->timestamp('sahodaya_approved_at')->nullable();
                $table->text('resolution_note')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index(['school_id', 'status']);
            });
        }

        if (! Schema::hasTable('school_user_event_scopes')) {
            Schema::create('school_user_event_scopes', function (Blueprint $table) {
                $table->id();
                $table->string('school_id');
                $table->unsignedBigInteger('user_id');
                $table->string('program_slug');
                $table->unsignedBigInteger('event_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['user_id', 'program_slug']);
                $table->index(['school_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('mcq_exam_series')) {
            Schema::create('mcq_exam_series', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('title');
                $table->unsignedBigInteger('academic_year_id')->nullable();
                $table->text('description')->nullable();
                $table->enum('status', ['draft', 'active', 'completed'])->default('draft');
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
            });
        }

        if (Schema::hasTable('mcq_exams')) {
            Schema::table('mcq_exams', function (Blueprint $table) {
                if (! Schema::hasColumn('mcq_exams', 'series_id')) {
                    $table->unsignedBigInteger('series_id')->nullable()->after('academic_year_id');
                }
                if (! Schema::hasColumn('mcq_exams', 'exam_level')) {
                    $table->unsignedTinyInteger('exam_level')->default(1)->after('series_id');
                }
                if (! Schema::hasColumn('mcq_exams', 'parent_exam_id')) {
                    $table->unsignedBigInteger('parent_exam_id')->nullable()->after('exam_level');
                }
                if (! Schema::hasColumn('mcq_exams', 'eligibility_mode')) {
                    $table->enum('eligibility_mode', ['open', 'cutoff_marks', 'top_rank', 'manual'])
                        ->default('open')->after('parent_exam_id');
                }
                if (! Schema::hasColumn('mcq_exams', 'cutoff_score')) {
                    $table->decimal('cutoff_score', 5, 2)->nullable()->after('eligibility_mode');
                }
                if (! Schema::hasColumn('mcq_exams', 'top_rank_count')) {
                    $table->unsignedInteger('top_rank_count')->nullable()->after('cutoff_score');
                }
                if (! Schema::hasColumn('mcq_exams', 'promotion_locked')) {
                    $table->boolean('promotion_locked')->default(false)->after('top_rank_count');
                }
                if (! Schema::hasColumn('mcq_exams', 'promoted_student_ids')) {
                    $table->json('promoted_student_ids')->nullable()->after('promotion_locked');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mcq_exams')) {
            Schema::table('mcq_exams', function (Blueprint $table) {
                foreach ([
                    'series_id', 'exam_level', 'parent_exam_id', 'eligibility_mode',
                    'cutoff_score', 'top_rank_count', 'promotion_locked', 'promoted_student_ids',
                ] as $col) {
                    if (Schema::hasColumn('mcq_exams', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        Schema::dropIfExists('mcq_exam_series');
        Schema::dropIfExists('school_user_event_scopes');
        Schema::dropIfExists('user_profile_change_requests');
        Schema::dropIfExists('school_lock_overrides');

        if (Schema::hasTable('student_edit_change_requests')) {
            Schema::table('student_edit_change_requests', function (Blueprint $table) {
                foreach ([
                    'school_approval_status', 'school_approved_by', 'school_approved_at',
                    'school_rejection_note', 'submitted_by_role', 'escalation_type',
                ] as $col) {
                    if (Schema::hasColumn('student_edit_change_requests', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('sahodaya_registration_windows')) {
            Schema::table('sahodaya_registration_windows', function (Blueprint $table) {
                foreach (['add_open', 'add_close', 'edit_open', 'edit_close'] as $col) {
                    if (Schema::hasColumn('sahodaya_registration_windows', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
