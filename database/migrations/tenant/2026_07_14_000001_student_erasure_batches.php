<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_erasure_batches')) {
            return;
        }

        Schema::create('student_erasure_batches', function (Blueprint $table) {
            $table->id();
            $table->string('school_id');
            $table->string('school_name');
            $table->unsignedInteger('student_count')->default(0);
            // Full point-in-time snapshot of every row removed by the erase action
            // (students + all dependent rows that don't survive a hard delete),
            // so a Restore can re-insert everything exactly as it was.
            $table->json('snapshot');
            $table->unsignedBigInteger('erased_by_user_id')->nullable();
            $table->string('erased_by_name')->nullable();
            $table->string('erased_by_email')->nullable();
            $table->timestamp('erased_at');
            $table->timestamp('restored_at')->nullable();
            $table->unsignedBigInteger('restored_by_user_id')->nullable();
            $table->string('restored_by_name')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'erased_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_erasure_batches');
    }
};
