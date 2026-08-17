<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fixes the O(N^2) student registration-number allocation flagged in
// docs/N1_AUDIT_SWEEP_2_2026_08_03.md (finding #1, performance fix dated
// 2026-08-15): StudentRegistrationNumberGenerator::generate() used to rescan
// every reg_no already issued to a Sahodaya for the year on every single call
// (Student::whereIn(...)->where('reg_no', 'like', ...)->pluck(...)->max()),
// which is O(N) per call and O(N^2) for a bulk import/backfill of N students.
//
// This table gives it an O(1) counter instead: one row per (sahodaya_id,
// year_suffix) holding the last sequence number issued, atomically
// incremented per call. It lives on the CENTRAL connection (like Tenant,
// AuditLog, ClassCategory) rather than per-tenant, because it's keyed by
// sahodaya_id and needs to be reachable the same way regardless of whether
// TENANCY_DATABASE_PER_SAHODAYA is on (one physical database per Sahodaya) or
// off (a single shared database) -- see App\Models\RegNoCounter.
//
// Deliberately NOT backfilled here from existing students.reg_no data: doing
// that would require this central migration to reach into every Sahodaya's
// own tenant database (only reachable via App\Support\TenancyDatabase, which
// depends on each Sahodaya's DB already being provisioned/reachable at
// migrate time), which is fragile for something that can't be executed or
// verified in this environment. Instead, App\Services\Students\
// StudentRegistrationNumberGenerator::generate() seeds a Sahodaya+year's
// counter row lazily, the first time it's asked for that pair with no
// existing counter row: it runs the old O(N) scan exactly once for that pair
// (not once per student), stores the resulting max as the seed, and every
// call after that is O(1). See the comment on generate() for details.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reg_no_counters', function (Blueprint $table) {
            $table->id();
            $table->string('sahodaya_id');
            $table->foreign('sahodaya_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('year_suffix', 8);
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();

            $table->unique(['sahodaya_id', 'year_suffix']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reg_no_counters');
    }
};
