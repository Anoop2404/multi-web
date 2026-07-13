<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('certificate_templates')) {
            Schema::table('certificate_templates', function (Blueprint $table) {
                if (! Schema::hasColumn('certificate_templates', 'background_path')) {
                    $table->string('background_path')->nullable()->after('template_file_path');
                }
                if (! Schema::hasColumn('certificate_templates', 'layout_json')) {
                    $table->json('layout_json')->nullable()->after('dynamic_fields_json');
                }
            });
        }

        if (Schema::hasTable('training_programs') && ! Schema::hasColumn('training_programs', 'certificate_template_id')) {
            Schema::table('training_programs', function (Blueprint $table) {
                $table->unsignedBigInteger('certificate_template_id')->nullable()->after('certificate_type');
                $table->index('certificate_template_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('training_programs') && Schema::hasColumn('training_programs', 'certificate_template_id')) {
            Schema::table('training_programs', function (Blueprint $table) {
                $table->dropIndex(['certificate_template_id']);
                $table->dropColumn('certificate_template_id');
            });
        }

        if (Schema::hasTable('certificate_templates')) {
            Schema::table('certificate_templates', function (Blueprint $table) {
                if (Schema::hasColumn('certificate_templates', 'layout_json')) {
                    $table->dropColumn('layout_json');
                }
                if (Schema::hasColumn('certificate_templates', 'background_path')) {
                    $table->dropColumn('background_path');
                }
            });
        }
    }
};
