<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('state_qualifier_entries', function (Blueprint $table) {
            $table->string('roll_number', 32)->nullable()->after('student_name');
        });

        Schema::table('state_fest_participants', function (Blueprint $table) {
            $table->string('roll_number', 32)->nullable()->after('student_name');
        });
    }

    public function down(): void
    {
        Schema::table('state_qualifier_entries', function (Blueprint $table) {
            $table->dropColumn('roll_number');
        });

        Schema::table('state_fest_participants', function (Blueprint $table) {
            $table->dropColumn('roll_number');
        });
    }
};
