<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_participation_policies', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_offstage_writing_per_student')->nullable()->after('max_offstage_per_student');
            $table->unsignedSmallInteger('max_offstage_drawing_per_student')->nullable()->after('max_offstage_writing_per_student');
        });
    }

    public function down(): void
    {
        Schema::table('fest_participation_policies', function (Blueprint $table) {
            $table->dropColumn(['max_offstage_writing_per_student', 'max_offstage_drawing_per_student']);
        });
    }
};
