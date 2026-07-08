<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('photo');
            }
            if (! Schema::hasColumn('students', 'verified_by_user_id')) {
                $table->unsignedBigInteger('verified_by_user_id')->nullable()->after('verified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'verified_by_user_id')) {
                $table->dropColumn('verified_by_user_id');
            }
            if (Schema::hasColumn('students', 'verified_at')) {
                $table->dropColumn('verified_at');
            }
        });
    }
};
