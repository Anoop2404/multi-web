<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notification_logs') && ! Schema::hasColumn('notification_logs', 'body')) {
            Schema::table('notification_logs', function (Blueprint $table) {
                $table->text('body')->nullable()->after('subject');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notification_logs') && Schema::hasColumn('notification_logs', 'body')) {
            Schema::table('notification_logs', function (Blueprint $table) {
                $table->dropColumn('body');
            });
        }
    }
};
