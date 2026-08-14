<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_sites', function (Blueprint $table) {
            $table->string('template_key', 80)->nullable();
            $table->string('template_version', 30)->nullable();
            $table->string('experience_version', 20)->default('v1');
            $table->string('homepage_mode', 40)->default('evergreen');
            $table->timestamp('homepage_mode_override_until')->nullable();
            $table->json('design_json')->nullable();
            $table->json('draft_template_json')->nullable();
        });

        Schema::table('site_sections', function (Blueprint $table) {
            $table->json('layout_json')->nullable();
            $table->json('published_layout_json')->nullable();
        });

        Schema::table('site_section_versions', function (Blueprint $table) {
            $table->json('layout_json')->nullable();
        });

        Schema::create('website_site_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('website_site_id');
            $table->string('action', 40);
            $table->string('template_key', 80)->nullable();
            $table->string('template_version', 30)->nullable();
            $table->json('snapshot_json');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['website_site_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_site_versions');

        Schema::table('site_section_versions', function (Blueprint $table) {
            $table->dropColumn('layout_json');
        });

        Schema::table('site_sections', function (Blueprint $table) {
            $table->dropColumn(['layout_json', 'published_layout_json']);
        });

        Schema::table('website_sites', function (Blueprint $table) {
            $table->dropColumn([
                'template_key', 'template_version', 'experience_version',
                'homepage_mode', 'homepage_mode_override_until', 'design_json', 'draft_template_json',
            ]);
        });
    }
};
