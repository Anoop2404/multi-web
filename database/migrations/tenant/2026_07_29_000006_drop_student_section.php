<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('students') || ! Schema::hasColumn('students', 'section')) {
            return;
        }

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('section');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('students') || Schema::hasColumn('students', 'section')) {
            return;
        }

        Schema::table('students', function (Blueprint $table) {
            $table->string('section', 20)->nullable()->after('school_class_id');
        });
    }
};
