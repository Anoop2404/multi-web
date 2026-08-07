<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_result_certification_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('certification_package_id');
            $table->foreign('certification_package_id', 'board_result_cert_reports_package_fk')
                ->references('id')->on('board_result_certification_packages')->cascadeOnDelete();
            $table->string('tenant_id');

            // summary | overall_toppers | subject_toppers | full_a1
            $table->string('report_type', 32);
            $table->unsignedBigInteger('stream_id')->nullable();

            // pending | generated | signed_uploaded | accepted | changes_requested | superseded
            $table->string('status', 32)->default('pending');

            $table->unsignedInteger('row_count')->nullable();
            $table->jsonb('data_snapshot')->nullable();
            $table->string('data_hash', 64)->nullable();

            $table->string('generated_pdf_path')->nullable();
            $table->string('generated_pdf_disk')->nullable();
            $table->timestamp('generated_at')->nullable();

            $table->string('signed_pdf_path')->nullable();
            $table->string('signed_pdf_disk')->nullable();
            $table->string('signed_pdf_hash', 64)->nullable();
            $table->unsignedBigInteger('signed_by_user_id')->nullable();
            $table->string('signer_role', 40)->nullable();
            $table->timestamp('signed_at')->nullable();

            $table->timestamp('accepted_at')->nullable();
            $table->text('review_notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['certification_package_id', 'report_type', 'stream_id'],
                'board_result_cert_reports_type_stream_unique'
            );
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_result_certification_reports');
    }
};
