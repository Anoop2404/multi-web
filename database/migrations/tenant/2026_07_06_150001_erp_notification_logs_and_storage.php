<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_logs')) {
            Schema::create('notification_logs', function (Blueprint $table) {
                $table->id();
                $table->string('template_key')->nullable();
                $table->string('notifiable_type')->nullable();
                $table->unsignedBigInteger('notifiable_id')->nullable();
                $table->string('to')->nullable();
                $table->string('subject')->nullable();
                $table->string('status', 20)->default('queued');
                $table->text('error')->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index(['template_key', 'created_at']);
            });
        }

        if (Schema::hasTable('school_documents') && ! Schema::hasColumn('school_documents', 'storage_disk')) {
            Schema::table('school_documents', function (Blueprint $table) {
                $table->string('storage_disk', 32)->nullable()->after('file_path');
            });
        }

        if (Schema::hasTable('export_jobs') && ! Schema::hasColumn('export_jobs', 'storage_disk')) {
            Schema::table('export_jobs', function (Blueprint $table) {
                $table->string('storage_disk', 32)->nullable()->after('file_path');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');

        if (Schema::hasTable('school_documents') && Schema::hasColumn('school_documents', 'storage_disk')) {
            Schema::table('school_documents', function (Blueprint $table) {
                $table->dropColumn('storage_disk');
            });
        }

        if (Schema::hasTable('export_jobs') && Schema::hasColumn('export_jobs', 'storage_disk')) {
            Schema::table('export_jobs', function (Blueprint $table) {
                $table->dropColumn('storage_disk');
            });
        }
    }
};
