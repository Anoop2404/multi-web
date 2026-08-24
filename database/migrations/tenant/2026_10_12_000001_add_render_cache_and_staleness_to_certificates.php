<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            // file_path (existing column) becomes the with-background variant, finally
            // written by RenderCertificateChunkJob; this is its without-background twin.
            if (! Schema::hasColumn('certificates', 'plain_file_path')) {
                $table->string('plain_file_path')->nullable()->after('file_path');
            }
            if (! Schema::hasColumn('certificates', 'storage_disk')) {
                $table->string('storage_disk', 32)->nullable()->after('plain_file_path');
            }
            // sha256 of the resolved render inputs (template + field values, excluding
            // certificate_date) — see FestCertificateService/CertificateStalenessMarker.
            if (! Schema::hasColumn('certificates', 'content_hash')) {
                $table->string('content_hash', 64)->nullable()->after('storage_disk');
            }
            if (! Schema::hasColumn('certificates', 'is_stale')) {
                $table->boolean('is_stale')->default(false)->after('content_hash');
            }
            if (! Schema::hasColumn('certificates', 'stale_checked_at')) {
                $table->timestamp('stale_checked_at')->nullable()->after('is_stale');
            }
            // Distinct from generated_at (set once, when the certificate row is first
            // created) — rendered_at tracks when the cached files were last (re)written.
            if (! Schema::hasColumn('certificates', 'rendered_at')) {
                $table->timestamp('rendered_at')->nullable()->after('stale_checked_at');
            }
        });

        // Composite index serves both today's missing cert_type-only bulk filter
        // (FestCertificateController::exportPayloadsForEvent()) and the new
        // "find stale rows of this type" query the regenerate-stale action needs — a
        // leading column already satisfies a plain cert_type = ? lookup on its own.
        if (! $this->hasIndex('certificates', 'certificates_cert_type_is_stale_index')) {
            Schema::table('certificates', function (Blueprint $table) {
                $table->index(['cert_type', 'is_stale']);
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('certificates', 'certificates_cert_type_is_stale_index')) {
            Schema::table('certificates', function (Blueprint $table) {
                $table->dropIndex('certificates_cert_type_is_stale_index');
            });
        }

        Schema::table('certificates', function (Blueprint $table) {
            foreach (['rendered_at', 'stale_checked_at', 'is_stale', 'content_hash', 'storage_disk', 'plain_file_path'] as $column) {
                if (Schema::hasColumn('certificates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))->pluck('name')->contains($indexName);
    }
};
