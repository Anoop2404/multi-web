<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_phase_regions', function (Blueprint $table) {
            // A region's own registration window, alongside its own conduct_start_at/
            // conduct_end_at above — falls back to the phase's registration_open/close
            // when null (see FestPhaseTopologyService::syncLeaf()).
            $table->timestamp('registration_open')->nullable()->after('conduct_end_at');
            $table->timestamp('registration_close')->nullable()->after('registration_open');
        });
    }

    public function down(): void
    {
        Schema::table('fest_phase_regions', function (Blueprint $table) {
            $table->dropColumn(['registration_open', 'registration_close']);
        });
    }
};
