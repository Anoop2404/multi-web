<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft reference only — no FK, since `states` lives on the central connection
 * and this migration runs against the separate `state` physical database.
 * Added to the two root tables only; children scope via their existing FK
 * chain back to these (intake_id / state_event_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('state_qualifier_intakes', function (Blueprint $table) {
            $table->uuid('state_id')->nullable()->after('state_program_id')->index();
        });

        Schema::table('state_fest_events', function (Blueprint $table) {
            $table->uuid('state_id')->nullable()->after('state_program_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('state_fest_events', function (Blueprint $table) {
            $table->dropColumn('state_id');
        });

        Schema::table('state_qualifier_intakes', function (Blueprint $table) {
            $table->dropColumn('state_id');
        });
    }
};
