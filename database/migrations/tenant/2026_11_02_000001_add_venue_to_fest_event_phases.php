<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_event_phases', function (Blueprint $table) {
            // Only meaningful for a non-regional phase — a regional phase's venue is set
            // per region instead, via fest_phase_regions.venue.
            $table->string('venue')->nullable()->after('payment_qr_code');
        });
    }

    public function down(): void
    {
        Schema::table('fest_event_phases', function (Blueprint $table) {
            $table->dropColumn('venue');
        });
    }
};
