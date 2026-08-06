<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight central tables for Sahodayas/schools that are NOT platform tenants but still
 * need to submit qualifiers for the State Kalolsavam — see docs/STATE_LEVEL_KALOTSAV_ROLLOUT_PLAN.md §2.1.
 *
 * Deliberately not a Stancl tenant and not the tenant-shaped schema: these are one-off,
 * low-engagement participants (dozens of them) who just need to hand over a roster, not run
 * the platform themselves. Access is a short code (see access_code), not a full user account —
 * same shape as how the manual already issues Sahodaya heads a password for state registration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_sahodayas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('state_program_id');
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('access_code', 20)->unique();
            $table->string('status', 20)->default('active'); // active | disabled
            $table->timestamps();

            $table->index('state_program_id');
        });

        Schema::create('external_schools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('external_sahodaya_id');
            $table->foreign('external_sahodaya_id')
                ->references('id')->on('external_sahodayas')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('access_code', 20)->unique();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('external_sahodaya_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_schools');
        Schema::dropIfExists('external_sahodayas');
    }
};
