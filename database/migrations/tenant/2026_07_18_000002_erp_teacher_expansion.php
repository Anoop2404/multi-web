<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            if (! Schema::hasColumn('teachers', 'gender')) {
                $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('name');
            }
            if (! Schema::hasColumn('teachers', 'dob')) {
                $table->date('dob')->nullable()->after('gender');
            }
            if (! Schema::hasColumn('teachers', 'mobile')) {
                $table->string('mobile', 20)->nullable()->after('email');
            }
            if (! Schema::hasColumn('teachers', 'address')) {
                $table->text('address')->nullable()->after('mobile');
            }
            if (! Schema::hasColumn('teachers', 'qualification')) {
                $table->string('qualification')->nullable()->after('designation');
            }
            if (! Schema::hasColumn('teachers', 'experience_years')) {
                $table->unsignedTinyInteger('experience_years')->nullable()->after('qualification');
            }
            if (! Schema::hasColumn('teachers', 'date_of_joining')) {
                $table->date('date_of_joining')->nullable()->after('experience_years');
            }
            if (! Schema::hasColumn('teachers', 'employment_status')) {
                $table->enum('employment_status', ['permanent', 'contract', 'temporary', 'probation'])->nullable()->after('date_of_joining');
            }
            if (! Schema::hasColumn('teachers', 'designation_id')) {
                $table->unsignedBigInteger('designation_id')->nullable()->after('designation');
            }
            if (! Schema::hasColumn('teachers', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('teachers', 'verified_by_user_id')) {
                $table->unsignedBigInteger('verified_by_user_id')->nullable()->after('verified_at');
            }
            if (! Schema::hasColumn('teachers', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('verified_by_user_id');
            }
            if (! Schema::hasColumn('teachers', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (! Schema::hasTable('teacher_subject')) {
            Schema::create('teacher_subject', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('teacher_id');
                $table->unsignedBigInteger('subject_id');
                $table->timestamps();

                $table->unique(['teacher_id', 'subject_id']);
                $table->foreign('teacher_id')->references('id')->on('teachers')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('teacher_school_class')) {
            Schema::create('teacher_school_class', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('teacher_id');
                $table->unsignedBigInteger('school_class_id');
                $table->string('section', 10)->nullable();
                $table->timestamps();

                $table->unique(['teacher_id', 'school_class_id', 'section']);
                $table->foreign('teacher_id')->references('id')->on('teachers')->cascadeOnDelete();
                $table->foreign('school_class_id')->references('id')->on('school_classes')->cascadeOnDelete();
            });
        }

        Schema::table('training_programs', function (Blueprint $table) {
            if (! Schema::hasColumn('training_programs', 'eligibility_config')) {
                $table->json('eligibility_config')->nullable()->after('fee_amount');
            }
            if (! Schema::hasColumn('training_programs', 'late_fee_amount')) {
                $table->decimal('late_fee_amount', 10, 2)->nullable()->after('fee_amount');
            }
            if (! Schema::hasColumn('training_programs', 'penalty_amount')) {
                $table->decimal('penalty_amount', 10, 2)->nullable()->after('late_fee_amount');
            }
        });

        Schema::table('fest_sports_age_group_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_sports_age_group_configs', 'age_category_id')) {
                $table->unsignedBigInteger('age_category_id')->nullable()->after('group_key');
            }
        });

        Schema::table('fee_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('fee_receipts', 'waiver_amount')) {
                $table->decimal('waiver_amount', 10, 2)->nullable()->after('amount');
            }
            if (! Schema::hasColumn('fee_receipts', 'waiver_reason')) {
                $table->string('waiver_reason')->nullable()->after('waiver_amount');
            }
            if (! Schema::hasColumn('fee_receipts', 'waived_by_user_id')) {
                $table->unsignedBigInteger('waived_by_user_id')->nullable()->after('waiver_reason');
            }
        });

        Schema::table('ledger_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('ledger_transactions', 'bank_account_id')) {
                $table->unsignedBigInteger('bank_account_id')->nullable()->after('account_head_id');
            }
            if (! Schema::hasColumn('ledger_transactions', 'reconciled_at')) {
                $table->timestamp('reconciled_at')->nullable()->after('posted_by');
            }
        });

        Schema::table('mcq_exams', function (Blueprint $table) {
            if (! Schema::hasColumn('mcq_exams', 'late_fee_amount')) {
                $table->decimal('late_fee_amount', 10, 2)->nullable()->after('fee_amount');
            }
            if (! Schema::hasColumn('mcq_exams', 'penalty_amount')) {
                $table->decimal('penalty_amount', 10, 2)->nullable()->after('late_fee_amount');
            }
            if (! Schema::hasColumn('mcq_exams', 'payment_deadline')) {
                $table->date('payment_deadline')->nullable()->after('penalty_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_school_class');
        Schema::dropIfExists('teacher_subject');

        Schema::table('teachers', function (Blueprint $table) {
            foreach ([
                'gender', 'dob', 'mobile', 'address', 'qualification', 'experience_years',
                'date_of_joining', 'employment_status', 'designation_id',
                'verified_at', 'verified_by_user_id', 'rejection_reason', 'deleted_at',
            ] as $col) {
                if (Schema::hasColumn('teachers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
