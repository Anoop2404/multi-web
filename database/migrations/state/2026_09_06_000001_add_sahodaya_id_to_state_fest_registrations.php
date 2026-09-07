<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalizes the submitting Sahodaya onto each state registration (from
 * qualifier_entry.intake.source_tenant_id) so limit/fee/report queries don't
 * need a two-hop, partly-nullable join. No backfill: this subsystem predates
 * launch and has no production rows yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('state_fest_registrations', function (Blueprint $table) {
            $table->string('sahodaya_id')->nullable()->after('qualifier_entry_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('state_fest_registrations', function (Blueprint $table) {
            $table->dropColumn('sahodaya_id');
        });
    }
};
