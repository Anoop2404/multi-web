<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uploaded_file_backups', function (Blueprint $table) {
            $table->id();
            $table->string('school_id')->nullable()->index();
            $table->string('purpose', 50)->index();
            $table->string('storage_disk', 20)->default('local');
            $table->string('storage_path');
            $table->string('original_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->nullableMorphs('related');
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('data_change_logs', function (Blueprint $table) {
            $table->id();
            $table->string('school_id')->nullable()->index();
            $table->string('log_name', 50)->nullable()->index();
            $table->string('action', 30)->index();
            $table->text('description');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->index(['subject_type', 'subject_id']);
            $table->unsignedBigInteger('causer_user_id')->nullable();
            $table->json('changes')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_change_logs');
        Schema::dropIfExists('uploaded_file_backups');
    }
};
