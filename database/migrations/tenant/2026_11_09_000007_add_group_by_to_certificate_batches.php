<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "All certificates (ZIP)" and its new "grouped by item"/"grouped by school" ZIP
 * variants (FestCertificateController::queueZipExport()) dispatch with the exact same
 * cert_type/published_only/item_id/school_id — group_by is the only thing that
 * distinguishes them. Without its own column, deleteSupersededBatches() would treat a
 * grouped export as superseding (and delete) an unrelated flat one already downloaded,
 * or vice versa — same conflation published_only was added to fix.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('certificate_batches')) {
            return;
        }

        if (! Schema::hasColumn('certificate_batches', 'group_by')) {
            Schema::table('certificate_batches', function (Blueprint $table) {
                $table->string('group_by')->nullable()->after('published_only');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('certificate_batches') && Schema::hasColumn('certificate_batches', 'group_by')) {
            Schema::table('certificate_batches', function (Blueprint $table) {
                $table->dropColumn('group_by');
            });
        }
    }
};
