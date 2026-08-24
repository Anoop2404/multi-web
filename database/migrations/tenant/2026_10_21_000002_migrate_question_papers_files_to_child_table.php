<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('question_papers', 'file_path')) {
            DB::table('question_papers')
                ->whereNotNull('file_path')
                ->orderBy('id')
                ->get(['id', 'file_path', 'storage_disk', 'original_name', 'mime_type', 'file_size'])
                ->each(function ($paper) {
                    DB::table('question_paper_files')->insert([
                        'question_paper_id' => $paper->id,
                        'file_path' => $paper->file_path,
                        'storage_disk' => $paper->storage_disk,
                        'original_name' => $paper->original_name,
                        'mime_type' => $paper->mime_type,
                        'file_size' => $paper->file_size,
                        'display_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
        }

        Schema::table('question_papers', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'storage_disk', 'original_name', 'mime_type', 'file_size']);
        });
    }

    public function down(): void
    {
        Schema::table('question_papers', function (Blueprint $table) {
            $table->string('file_path')->nullable();
            $table->string('storage_disk', 40)->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
        });
    }
};
