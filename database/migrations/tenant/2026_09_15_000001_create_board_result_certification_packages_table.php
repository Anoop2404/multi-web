<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_result_certification_packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('board_result_id');
            $table->foreign('board_result_id')->references('id')->on('board_results')->cascadeOnDelete();
            $table->string('tenant_id');
            $table->string('academic_year');
            $table->unsignedTinyInteger('class');
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 40)->default('draft');

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

            $table->unsignedBigInteger('submitted_by_user_id')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->unsignedBigInteger('returned_by_user_id')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->text('return_reason')->nullable();

            $table->timestamp('superseded_at')->nullable();

            $table->timestamps();

            $table->unique(['board_result_id', 'version'], 'board_result_cert_pkg_result_version_unique');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'academic_year', 'class']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_result_certification_packages');
    }
};
