<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mcq_exams')) {
            Schema::table('mcq_exams', function (Blueprint $table) {
                if (! Schema::hasColumn('mcq_exams', 'code')) {
                    $table->string('code', 64)->nullable()->after('title');
                }
                if (! Schema::hasColumn('mcq_exams', 'registration_opens_at')) {
                    $table->timestamp('registration_opens_at')->nullable()->after('scheduled_at');
                }
                if (! Schema::hasColumn('mcq_exams', 'registration_closes_at')) {
                    $table->timestamp('registration_closes_at')->nullable()->after('registration_opens_at');
                }
                if (! Schema::hasColumn('mcq_exams', 'result_date')) {
                    $table->date('result_date')->nullable()->after('registration_closes_at');
                }
            });

            // Unique exam code per tenant (multiple NULLs allowed).
            if (! Schema::hasIndex('mcq_exams', 'mcq_exams_tenant_id_code_unique')) {
                Schema::table('mcq_exams', function (Blueprint $table) {
                    $table->unique(['tenant_id', 'code'], 'mcq_exams_tenant_id_code_unique');
                });
            }
        }

        if (Schema::hasTable('mcq_questions')) {
            Schema::table('mcq_questions', function (Blueprint $table) {
                if (! Schema::hasColumn('mcq_questions', 'marks')) {
                    $table->decimal('marks', 8, 2)->default(1)->after('correct_option_key');
                }
                if (! Schema::hasColumn('mcq_questions', 'negative_mark')) {
                    $table->decimal('negative_mark', 8, 2)->default(0)->after('marks');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mcq_exams')) {
            if (Schema::hasIndex('mcq_exams', 'mcq_exams_tenant_id_code_unique')) {
                Schema::table('mcq_exams', function (Blueprint $table) {
                    $table->dropUnique('mcq_exams_tenant_id_code_unique');
                });
            }

            Schema::table('mcq_exams', function (Blueprint $table) {
                foreach (['result_date', 'registration_closes_at', 'registration_opens_at', 'code'] as $column) {
                    if (Schema::hasColumn('mcq_exams', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('mcq_questions')) {
            Schema::table('mcq_questions', function (Blueprint $table) {
                foreach (['negative_mark', 'marks'] as $column) {
                    if (Schema::hasColumn('mcq_questions', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
