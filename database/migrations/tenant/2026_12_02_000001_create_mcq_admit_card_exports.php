<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcq_admit_card_exports', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('exam_id')->constrained('mcq_exams')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('scope_description');
            $table->json('registration_ids');
            $table->unsignedInteger('total_count');
            $table->string('filename');
            $table->string('storage_disk');
            $table->string('file_path')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'exam_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcq_admit_card_exports');
    }
};
