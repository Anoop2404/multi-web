<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploaded_file_backups', function (Blueprint $table) {
            $table->string('status', 20)->nullable()->after('metadata');
            $table->unsignedInteger('total_rows')->nullable()->after('status');
            $table->unsignedInteger('imported_count')->nullable()->after('total_rows');
            $table->unsignedInteger('error_count')->nullable()->after('imported_count');
            $table->json('errors')->nullable()->after('error_count');
        });
    }

    public function down(): void
    {
        Schema::table('uploaded_file_backups', function (Blueprint $table) {
            $table->dropColumn(['status', 'total_rows', 'imported_count', 'error_count', 'errors']);
        });
    }
};
