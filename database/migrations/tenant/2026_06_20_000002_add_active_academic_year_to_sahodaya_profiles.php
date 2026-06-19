<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sahodaya_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('sahodaya_profiles', 'active_academic_year')) {
                $table->string('active_academic_year', 10)->nullable()->after('prefixes_locked');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sahodaya_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('sahodaya_profiles', 'active_academic_year')) {
                $table->dropColumn('active_academic_year');
            }
        });
    }
};
