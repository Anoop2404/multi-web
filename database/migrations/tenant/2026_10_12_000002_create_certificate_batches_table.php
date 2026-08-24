<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('certificate_batches')) {
            return;
        }

        Schema::create('certificate_batches', function (Blueprint $table) {
            $table->id();
            // tenant_id kept even though certificates itself has no such column, matching
            // certificate_templates.tenant_id — defensive scoping for non-database-per-
            // sahodaya tenancy mode (see config('tenancy.database_per_sahodaya')).
            $table->string('tenant_id');
            $table->unsignedBigInteger('event_id')->nullable();
            $table->foreign('event_id')->references('id')->on('fest_events')->nullOnDelete();

            // 'zip_export' is reserved for forward-compat (async ZIP packaging of already-
            // cached files, should that ever need the same batch-tracking treatment) —
            // not dispatched by anything in this pass.
            $table->string('batch_type', 20)->default('generate');

            // Scope this run resolved against, mirroring exactly the filter dimensions
            // FestCertificateController::exportPayloadsForEvent() already supports.
            $table->string('cert_type', 30)->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->foreign('item_id')->references('id')->on('fest_event_items')->nullOnDelete();
            // No FK — schools are Tenant rows in the central database, unreachable from a
            // per-Sahodaya tenant database's foreign key constraints (see
            // fest_registrations.school_id for the same plain-string precedent).
            $table->string('school_id')->nullable();
            // Ad-hoc certificate_ids selections, stored so the run's scope stays
            // reproducible even if the underlying eligible set changes later. A few
            // thousand ints is small enough to store inline.
            $table->json('certificate_ids_json')->nullable();
            $table->string('scope_description')->nullable();

            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('succeeded_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);

            // completed_with_errors is a deliberately distinct terminal state so a
            // "4,982 succeeded, 3 failed" run reads differently from a clean run.
            $table->string('status', 30)->default('pending');
            $table->text('error')->nullable();
            // Capped list of {certificate_id, name, reason}, capped at ~100 entries —
            // matches the existing error-cap style in ImportStudentsJob.
            $table->json('failed_items_json')->nullable();

            // Reserved for batch_type = 'zip_export'.
            $table->string('file_path')->nullable();
            $table->string('storage_disk', 32)->nullable();

            // Debugging breadcrumb to the underlying Bus::batch() run (job_batches.id) —
            // the UI never reads job_batches directly, this is purely for support/ops.
            $table->string('queued_job_batch_id', 36)->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_batches');
    }
};
