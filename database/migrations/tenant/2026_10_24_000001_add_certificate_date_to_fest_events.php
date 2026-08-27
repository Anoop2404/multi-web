<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable certificate_date to fest_events — an admin-settable override for the
 * date printed on generated certificates. FestCertificateService::resolveFieldValues()
 * falls back to event_end/event_start, then now(), when this is unset.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fest_events')) {
            return;
        }

        if (! Schema::hasColumn('fest_events', 'certificate_date')) {
            Schema::table('fest_events', function (Blueprint $table) {
                $table->date('certificate_date')
                    ->nullable()
                    ->after('event_end')
                    ->comment('Admin override for the date printed on certificates; falls back to event_end/event_start/now() when unset.');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_events') && Schema::hasColumn('fest_events', 'certificate_date')) {
            Schema::table('fest_events', function (Blueprint $table) {
                $table->dropColumn('certificate_date');
            });
        }
    }
};
