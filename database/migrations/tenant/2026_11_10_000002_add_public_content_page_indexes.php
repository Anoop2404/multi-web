<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index audit, 2026-09-14 — public (non-fest) pages: galleries, circulars, MCQ archive,
 * training QR self-registration. Same root cause as the fest migration alongside this
 * one: Postgres never auto-indexes a plain `$table->foreignId('x')->constrained()`.
 *
 * 1. gallery_items(album_id, display_order) — GalleryAlbumController::show() loads
 *    every item of an album via GalleryAlbum::items() (hasMany, ordered by
 *    display_order). album_id had no index at all — every album page view was a full
 *    table scan of gallery_items across the whole tenant.
 * 2. circulars(tenant_id, issued_date) — the existing (tenant_id, category,
 *    issued_date) index doesn't help: CircularController::index() and
 *    SahodayaPublicData::latestCirculars()/announcements()/resources() (the latter
 *    pulled into the default Sahodaya navbar, so it runs on nearly every public page,
 *    not just /circulars) order by issued_date without filtering category, leaving that
 *    column stranded behind a skipped middle column in the old index.
 * 3. gallery_albums(tenant_id, display_order) — GalleryAlbumController::index() orders
 *    by display_order; the only existing index is unique(tenant_id, slug), which
 *    doesn't serve this ordering.
 * 4. mcq_exams(tenant_id, scheduled_at) — McqArchiveController::index() orders by
 *    scheduled_at; the existing (tenant_id, status) index doesn't cover it since status
 *    isn't filtered here.
 * 5. teachers — TrainingPublicRegistrationService::findExistingTeacher(), hit on every
 *    QR self-registration submit, looks up by lower(email) or by mobile within a
 *    tenant. A plain index on email wouldn't be used for the LOWER() predicate, so this
 *    adds an expression index on lower(email) plus a plain composite for mobile.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('gallery_items', ['album_id', 'display_order'], 'gallery_items_album_order_idx');
        $this->addIndex('circulars', ['tenant_id', 'issued_date'], 'circulars_tenant_issued_idx');
        $this->addIndex('gallery_albums', ['tenant_id', 'display_order'], 'gallery_albums_tenant_order_idx');
        $this->addIndex('mcq_exams', ['tenant_id', 'scheduled_at'], 'mcq_exams_tenant_scheduled_idx');
        $this->addIndex('teachers', ['tenant_id', 'mobile'], 'teachers_tenant_mobile_idx');
        $this->addTeacherEmailExpressionIndex();
    }

    public function down(): void
    {
        $this->dropIndex('gallery_items', 'gallery_items_album_order_idx');
        $this->dropIndex('circulars', 'circulars_tenant_issued_idx');
        $this->dropIndex('gallery_albums', 'gallery_albums_tenant_order_idx');
        $this->dropIndex('mcq_exams', 'mcq_exams_tenant_scheduled_idx');
        $this->dropIndex('teachers', 'teachers_tenant_mobile_idx');

        if (Schema::hasTable('teachers') && Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('DROP INDEX IF EXISTS teachers_tenant_lower_email_idx');
        }
    }

    /** @param  list<string>  $columns */
    private function addIndex(string $table, array $columns, string $name): void
    {
        if (Schema::hasTable($table) && ! Schema::hasIndex($table, $name)) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
                $blueprint->index($columns, $name);
            });
        }
    }

    private function dropIndex(string $table, string $name): void
    {
        if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
            Schema::table($table, function (Blueprint $blueprint) use ($name) {
                $blueprint->dropIndex($name);
            });
        }
    }

    private function addTeacherEmailExpressionIndex(): void
    {
        if (! Schema::hasTable('teachers') || Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (Schema::hasIndex('teachers', 'teachers_tenant_lower_email_idx')) {
            return;
        }

        try {
            Schema::getConnection()->statement(
                'CREATE INDEX teachers_tenant_lower_email_idx ON teachers (tenant_id, lower(email))'
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
};
