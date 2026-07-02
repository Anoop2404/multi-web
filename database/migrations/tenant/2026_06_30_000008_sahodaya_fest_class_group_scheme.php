<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sahodaya_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('sahodaya_profiles', 'fest_class_group_scheme')) {
                $table->string('fest_class_group_scheme', 20)->default('cbse')->after('active_academic_year');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sahodaya_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('sahodaya_profiles', 'fest_class_group_scheme')) {
                $table->dropColumn('fest_class_group_scheme');
            }
        });
    }
};
