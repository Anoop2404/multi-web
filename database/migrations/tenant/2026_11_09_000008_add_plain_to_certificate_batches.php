<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Same conflation group_by's own migration (2026_11_09_000007) fixed, one dimension
 * earlier: "Merit winners only (ZIP)" and "Merit winners only — no background (ZIP)"
 * dispatch with the exact same cert_type/published_only/group_by/item_id/school_id —
 * plain was never its own column, so deleteSupersededBatches() would treat a plain
 * export as superseding (and delete) an already-downloaded non-plain one of the same
 * scope, or vice versa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('certificate_batches')) {
            return;
        }

        if (! Schema::hasColumn('certificate_batches', 'plain')) {
            Schema::table('certificate_batches', function (Blueprint $table) {
                $table->boolean('plain')->default(false)->after('group_by');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('certificate_batches') && Schema::hasColumn('certificate_batches', 'plain')) {
            Schema::table('certificate_batches', function (Blueprint $table) {
                $table->dropColumn('plain');
            });
        }
    }
};
