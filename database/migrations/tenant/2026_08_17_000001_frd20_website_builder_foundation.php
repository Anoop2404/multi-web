<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('website_sites')) {
            Schema::create('website_sites', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('name', 120);
                $table->string('slug', 80);
                $table->boolean('is_primary')->default(false);
                $table->boolean('is_active')->default(true);
                $table->json('seo_json')->nullable();
                $table->timestamps();
                $table->unique(['tenant_id', 'slug']);
                $table->index(['tenant_id', 'is_primary']);
            });
        }

        if (Schema::hasTable('site_sections')) {
            Schema::table('site_sections', function (Blueprint $table) {
                if (! Schema::hasColumn('site_sections', 'site_id')) {
                    $table->unsignedBigInteger('site_id')->nullable()->after('tenant_id');
                    $table->index('site_id');
                }
                if (! Schema::hasColumn('site_sections', 'status')) {
                    $table->string('status', 20)->default('published')->after('is_active');
                }
                if (! Schema::hasColumn('site_sections', 'published_config')) {
                    $table->json('published_config')->nullable()->after('config');
                }
                if (! Schema::hasColumn('site_sections', 'published_at')) {
                    $table->timestamp('published_at')->nullable()->after('published_config');
                }
                if (! Schema::hasColumn('site_sections', 'updated_by')) {
                    $table->unsignedBigInteger('updated_by')->nullable()->after('published_at');
                }
            });

            // Backfill: existing live sections stay published.
            DB::table('site_sections')->orderBy('id')->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $updates = [];
                    if (empty($row->status) || $row->status === 'draft') {
                        $updates['status'] = 'published';
                    }
                    if ($row->published_config === null && $row->config !== null) {
                        $updates['published_config'] = $row->config;
                        $updates['published_at'] = $row->updated_at ?? now();
                    }
                    if ($updates !== []) {
                        DB::table('site_sections')->where('id', $row->id)->update($updates);
                    }
                }
            });
        }

        if (! Schema::hasTable('site_section_versions')) {
            Schema::create('site_section_versions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('site_section_id');
                $table->string('variant', 50)->nullable();
                $table->json('config')->nullable();
                $table->string('note', 255)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index('site_section_id');
            });
        }

        if (! Schema::hasTable('site_forms')) {
            Schema::create('site_forms', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->unsignedBigInteger('site_id')->nullable();
                $table->string('name', 120);
                $table->string('slug', 80);
                $table->json('fields_json');
                $table->string('success_message', 500)->nullable();
                $table->string('notify_email')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('honeypot_enabled')->default(true);
                $table->timestamps();
                $table->unique(['tenant_id', 'slug']);
            });
        }

        if (! Schema::hasTable('site_form_submissions')) {
            Schema::create('site_form_submissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('site_form_id');
                $table->json('payload_json');
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->boolean('is_spam')->default(false);
                $table->timestamps();
                $table->index('site_form_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_form_submissions');
        Schema::dropIfExists('site_forms');
        Schema::dropIfExists('site_section_versions');

        if (Schema::hasTable('site_sections')) {
            Schema::table('site_sections', function (Blueprint $table) {
                foreach (['site_id', 'status', 'published_config', 'published_at', 'updated_by'] as $col) {
                    if (Schema::hasColumn('site_sections', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        Schema::dropIfExists('website_sites');
    }
};
