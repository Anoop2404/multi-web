<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('export_jobs')) {
            Schema::create('export_jobs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('export_type', 80);
                $table->string('filename');
                $table->string('file_path')->nullable();
                $table->unsignedInteger('row_count')->default(0);
                $table->string('status', 20)->default('pending');
                $table->text('error')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};
