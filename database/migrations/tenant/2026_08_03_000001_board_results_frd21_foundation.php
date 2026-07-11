<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_results', function (Blueprint $table) {
            $table->string('examination_type', 16)->default('AISSE')->after('class');
            $table->decimal('highest_mark', 6, 2)->nullable()->after('first_class');
            $table->decimal('average_mark', 6, 2)->nullable()->after('highest_mark');
            $table->text('remarks')->nullable()->after('average_mark');
            $table->string('result_pdf_path')->nullable()->after('remarks');
            $table->string('result_pdf_disk')->nullable()->after('result_pdf_path');
            $table->jsonb('attachment_paths')->nullable()->after('result_pdf_disk');
            $table->string('status', 32)->default('draft')->after('attachment_paths');
            $table->unsignedBigInteger('submitted_by')->nullable()->after('status');
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            $table->unsignedBigInteger('verified_by')->nullable()->after('submitted_at');
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->unsignedBigInteger('approved_by')->nullable()->after('verified_at');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->timestamp('published_at')->nullable()->after('approved_at');
            $table->text('rejection_reason')->nullable()->after('published_at');
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable()->after('rejection_reason');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_user_id');
        });

        // Backfill examination_type from class (AISSE=X, AISSCE=XII).
        DB::table('board_results')->where('class', 12)->update(['examination_type' => 'AISSCE']);
        DB::table('board_results')->where('class', '!=', 12)->update(['examination_type' => 'AISSE']);

        Schema::table('board_results', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'class', 'academic_year']);
            $table->unique(
                ['tenant_id', 'class', 'examination_type', 'academic_year'],
                'board_results_tenant_class_exam_year_unique'
            );
            $table->index(['status', 'academic_year']);
        });

        Schema::table('toppers', function (Blueprint $table) {
            $table->string('admission_no', 64)->nullable()->after('name');
            $table->string('roll_no', 64)->nullable()->after('admission_no');
        });

        Schema::create('board_result_uploads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('board_result_id');
            $table->foreign('board_result_id')->references('id')->on('board_results')->cascadeOnDelete();
            $table->string('tenant_id');
            $table->unsignedInteger('version')->default(1);
            $table->string('file_path');
            $table->string('storage_disk')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_type', 32)->default('pdf'); // pdf | attachment
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
            $table->unique(['board_result_id', 'version', 'file_type'], 'board_result_uploads_version_unique');
            $table->index(['tenant_id', 'board_result_id']);
        });

        Schema::create('board_result_rankings', function (Blueprint $table) {
            $table->id();
            $table->string('sahodaya_id');
            $table->string('academic_year');
            $table->string('examination_type', 16)->nullable();
            $table->unsignedTinyInteger('class')->nullable();
            $table->string('scope', 64); // overall_pass_percent | overall | stream | subject
            $table->string('entity_type', 32)->default('school'); // school | student
            $table->string('entity_id');
            $table->unsignedBigInteger('board_result_id')->nullable();
            $table->unsignedInteger('rank');
            $table->decimal('score', 10, 4)->nullable();
            $table->string('tie_rule_applied', 64)->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->index(['sahodaya_id', 'academic_year', 'scope', 'rank'], 'board_result_rankings_lookup');
            $table->index(['board_result_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_result_rankings');
        Schema::dropIfExists('board_result_uploads');

        Schema::table('toppers', function (Blueprint $table) {
            $table->dropColumn(['admission_no', 'roll_no']);
        });

        Schema::table('board_results', function (Blueprint $table) {
            $table->dropUnique('board_results_tenant_class_exam_year_unique');
            $table->unique(['tenant_id', 'class', 'academic_year']);
            $table->dropIndex(['status', 'academic_year']);
            $table->dropColumn([
                'examination_type',
                'highest_mark',
                'average_mark',
                'remarks',
                'result_pdf_path',
                'result_pdf_disk',
                'attachment_paths',
                'status',
                'submitted_by',
                'submitted_at',
                'verified_by',
                'verified_at',
                'approved_by',
                'approved_at',
                'published_at',
                'rejection_reason',
                'reviewed_by_user_id',
                'reviewed_at',
            ]);
        });
    }
};
