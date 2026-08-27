<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_batches', function (Blueprint $table) {
            // The download-facing filename for a completed batch_type='zip_export' run —
            // file_path/storage_disk (already reserved for this) locate the bytes, but
            // give no user-facing name; recomputing one at download time would need the
            // exact published_only/cert_type/plain combination the job ran with, which
            // isn't otherwise fully recoverable from the stored scope columns.
            if (! Schema::hasColumn('certificate_batches', 'result_filename')) {
                $table->string('result_filename')->nullable()->after('storage_disk');
            }
        });
    }

    public function down(): void
    {
        Schema::table('certificate_batches', function (Blueprint $table) {
            if (Schema::hasColumn('certificate_batches', 'result_filename')) {
                $table->dropColumn('result_filename');
            }
        });
    }
};
