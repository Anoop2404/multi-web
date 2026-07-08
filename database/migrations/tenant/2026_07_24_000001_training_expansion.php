<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('training_programs')) {
            Schema::table('training_programs', function (Blueprint $table) {
                if (! Schema::hasColumn('training_programs', 'venue')) {
                    $table->string('venue')->nullable()->after('description');
                }
                if (! Schema::hasColumn('training_programs', 'start_date')) {
                    $table->date('start_date')->nullable()->after('venue');
                }
                if (! Schema::hasColumn('training_programs', 'end_date')) {
                    $table->date('end_date')->nullable()->after('start_date');
                }
                if (! Schema::hasColumn('training_programs', 'allow_teacher_self_registration')) {
                    $table->boolean('allow_teacher_self_registration')->default(true)->after('max_participants');
                }
            });
        }

        if (Schema::hasTable('certificate_templates')) {
            Schema::table('certificate_templates', function (Blueprint $table) {
                if (! Schema::hasColumn('certificate_templates', 'title')) {
                    $table->string('title')->nullable()->after('certificate_type');
                }
                if (! Schema::hasColumn('certificate_templates', 'body')) {
                    $table->text('body')->nullable()->after('title');
                }
                if (! Schema::hasColumn('certificate_templates', 'logo_path')) {
                    $table->string('logo_path')->nullable()->after('body');
                }
                if (! Schema::hasColumn('certificate_templates', 'seal_path')) {
                    $table->string('seal_path')->nullable()->after('logo_path');
                }
                if (! Schema::hasColumn('certificate_templates', 'signatories')) {
                    // [{ name, designation, signature_path }]
                    $table->json('signatories')->nullable()->after('seal_path');
                }
                if (! Schema::hasColumn('certificate_templates', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('signatories');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('training_programs')) {
            Schema::table('training_programs', function (Blueprint $table) {
                foreach (['venue', 'start_date', 'end_date', 'allow_teacher_self_registration'] as $col) {
                    if (Schema::hasColumn('training_programs', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('certificate_templates')) {
            Schema::table('certificate_templates', function (Blueprint $table) {
                foreach (['title', 'body', 'logo_path', 'seal_path', 'signatories', 'is_active'] as $col) {
                    if (Schema::hasColumn('certificate_templates', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
