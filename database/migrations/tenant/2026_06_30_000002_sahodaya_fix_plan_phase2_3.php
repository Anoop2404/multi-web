<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mcq_marks') && ! Schema::hasColumn('mcq_marks', 'rank')) {
            Schema::table('mcq_marks', function (Blueprint $table) {
                $table->unsignedInteger('rank')->nullable()->after('grade');
            });
        }

        if (Schema::hasTable('mcq_exams') && ! Schema::hasColumn('mcq_exams', 'eligibility_config')) {
            Schema::table('mcq_exams', function (Blueprint $table) {
                $table->json('eligibility_config')->nullable()->after('settings_json');
            });
        }

        if (! Schema::hasTable('mcq_school_fees')) {
            Schema::create('mcq_school_fees', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('exam_id');
                $table->string('school_id');
                $table->unsignedInteger('student_count')->default(0);
                $table->decimal('total_due', 10, 2)->default(0);
                $table->unsignedBigInteger('fee_receipt_id')->nullable();
                $table->string('status', 30)->default('pending');
                $table->timestamps();
                $table->unique(['exam_id', 'school_id']);
                $table->foreign('exam_id')->references('id')->on('mcq_exams')->cascadeOnDelete();
                $table->foreign('fee_receipt_id')->references('id')->on('fee_receipts')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('training_school_fees')) {
            Schema::create('training_school_fees', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('program_id');
                $table->string('school_id');
                $table->unsignedInteger('teacher_count')->default(0);
                $table->decimal('total_due', 10, 2)->default(0);
                $table->unsignedBigInteger('fee_receipt_id')->nullable();
                $table->string('status', 30)->default('pending');
                $table->timestamps();
                $table->unique(['program_id', 'school_id']);
                $table->foreign('program_id')->references('id')->on('training_programs')->cascadeOnDelete();
                $table->foreign('fee_receipt_id')->references('id')->on('fee_receipts')->nullOnDelete();
            });
        }

        if (Schema::hasTable('fest_participation_policies') && ! Schema::hasColumn('fest_participation_policies', 'require_school_qualification')) {
            Schema::table('fest_participation_policies', function (Blueprint $table) {
                $table->boolean('require_school_qualification')->default(false)->after('require_fee_before_approval');
            });
        }

        if (Schema::hasTable('registrations') && ! Schema::hasColumn('registrations', 'fee_override')) {
            Schema::table('registrations', function (Blueprint $table) {
                $table->json('fee_override')->nullable()->after('membership_fee_amount');
            });
        }

        if (Schema::hasTable('fest_school_event_fees') && ! Schema::hasColumn('fest_school_event_fees', 'override_amount')) {
            Schema::table('fest_school_event_fees', function (Blueprint $table) {
                $table->decimal('override_amount', 10, 2)->nullable()->after('total_due');
            });
        }

        if (Schema::hasTable('membership_fee_slabs') && ! Schema::hasColumn('membership_fee_slabs', 'late_fee_amount')) {
            Schema::table('membership_fee_slabs', function (Blueprint $table) {
                $table->decimal('late_fee_amount', 10, 2)->nullable()->after('due_date');
            });
        }

        if (Schema::hasTable('fest_point_rules') && ! Schema::hasColumn('fest_point_rules', 'points_table')) {
            Schema::table('fest_point_rules', function (Blueprint $table) {
                $table->string('points_table', 30)->default('custom')->after('is_group');
            });
        }

        if (! Schema::hasTable('bank_accounts')) {
            Schema::create('bank_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('account_name');
                $table->string('bank_name')->nullable();
                $table->string('account_no')->nullable();
                $table->string('ifsc')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('fee_receipts') && ! Schema::hasColumn('fee_receipts', 'bank_account_id')) {
            Schema::table('fee_receipts', function (Blueprint $table) {
                $table->unsignedBigInteger('bank_account_id')->nullable()->after('amount');
            });
        }

        if (Schema::hasTable('membership_payments') && ! Schema::hasColumn('membership_payments', 'bank_account_id')) {
            Schema::table('membership_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('bank_account_id')->nullable()->after('amount');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('training_school_fees');
        Schema::dropIfExists('mcq_school_fees');
        Schema::dropIfExists('bank_accounts');
    }
};
