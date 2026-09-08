<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_bank_documents', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            // MasterClass lives on the central connection — a different physical database
            // from this Sahodaya's own tables, so this is a soft reference (no DB-level FK),
            // same pattern as FestEventItem.state_program_item_id elsewhere in this codebase.
            $table->unsignedBigInteger('master_class_id')->nullable();
            $table->string('title');
            $table->string('subject')->nullable();
            $table->string('file_path');
            $table->string('academic_year', 20)->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'master_class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_bank_documents');
    }
};
