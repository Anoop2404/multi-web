<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_event_phases', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_event_phases', 'student_registration_fee')) {
                $table->decimal('student_registration_fee', 10, 2)->nullable()->after('school_registration_fee_share');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fest_event_phases', function (Blueprint $table) {
            if (Schema::hasColumn('fest_event_phases', 'student_registration_fee')) {
                $table->dropColumn('student_registration_fee');
            }
        });
    }
};
