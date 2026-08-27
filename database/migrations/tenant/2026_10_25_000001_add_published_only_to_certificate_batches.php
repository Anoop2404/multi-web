<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Merit winners only (ZIP)" and "All certificates (ZIP)" both dispatch with cert_type
 * null (published_only is a separate boolean, applied by filtering the resolved
 * certificate set rather than by querying a distinct cert_type) — the two were
 * previously indistinguishable on the batch row itself, both landing on the exact same
 * scope_description ("Whole event"). Needed so a batch's own scope signature (used to
 * find and clean up a superseded prior run of "the same kind" before starting a new one)
 * doesn't conflate them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('certificate_batches')) {
            return;
        }

        if (! Schema::hasColumn('certificate_batches', 'published_only')) {
            Schema::table('certificate_batches', function (Blueprint $table) {
                $table->boolean('published_only')->default(false)->after('cert_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('certificate_batches') && Schema::hasColumn('certificate_batches', 'published_only')) {
            Schema::table('certificate_batches', function (Blueprint $table) {
                $table->dropColumn('published_only');
            });
        }
    }
};
