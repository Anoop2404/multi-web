<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'login_code')) {
                $table->dropUnique(['login_code']);
                $table->dropColumn('login_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'login_code')) {
                $table->string('login_code', 30)->nullable()->unique()->after('reg_no');
            }
        });
    }
};
