<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_event_phases', function (Blueprint $table) {
            $table->text('payment_instructions')->nullable()->after('student_registration_fee');
            $table->string('payment_qr_code')->nullable()->after('payment_instructions');
        });
    }

    public function down(): void
    {
        Schema::table('fest_event_phases', function (Blueprint $table) {
            $table->dropColumn(['payment_instructions', 'payment_qr_code']);
        });
    }
};
