<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_paper_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_paper_id');
            $table->string('file_path');
            $table->string('storage_disk', 40);
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->foreign('question_paper_id')->references('id')->on('question_papers')->cascadeOnDelete();
            $table->index(['question_paper_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_paper_files');
    }
};
