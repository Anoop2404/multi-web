<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event certificate signatories ("Venue Convenor", "Host Principal", ...). Who signs
 * changes with every host venue, so it can't live on a template shared across events:
 * each entry is {key, label, name, designation, school, signature_path}, and the
 * certificate template decides where each labelled block is printed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fest_events', 'certificate_signatories')) {
            Schema::table('fest_events', function (Blueprint $table) {
                $table->json('certificate_signatories')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fest_events', 'certificate_signatories')) {
            Schema::table('fest_events', function (Blueprint $table) {
                $table->dropColumn('certificate_signatories');
            });
        }
    }
};
