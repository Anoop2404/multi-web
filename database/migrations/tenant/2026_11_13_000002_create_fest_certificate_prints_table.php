<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which participation certificates have been sent to print, per student. "Print complete
 * students" (Certificates page) prints only the students whose every item has published
 * results, records them here under one run id, and never prints the same student twice --
 * the per-school report sheet is built from a run's rows plus the students still pending.
 * Participation certificates are one per person and shared across an event's family, so
 * the row hangs off the certificate and the root event.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fest_certificate_prints')) {
            return;
        }

        Schema::create('fest_certificate_prints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('certificate_id')->unique();
            $table->string('school_id', 64)->index();
            $table->uuid('run_uuid')->index();
            $table->unsignedBigInteger('printed_by_user_id')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_certificate_prints');
    }
};
