<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_programs')) {
            return;
        }

        Schema::table('training_programs', function (Blueprint $table) {
            if (! Schema::hasColumn('training_programs', 'code')) {
                $table->string('code')->nullable()->after('title');
            }
            if (! Schema::hasColumn('training_programs', 'banner_image_path')) {
                $table->string('banner_image_path')->nullable()->after('description');
            }
            if (! Schema::hasColumn('training_programs', 'allow_school_nomination')) {
                $table->boolean('allow_school_nomination')->default(true)->after('allow_teacher_self_registration');
            }
        });

        // Unique program code per tenant (multiple NULLs allowed).
        if (! Schema::hasIndex('training_programs', 'training_programs_tenant_id_code_unique')) {
            Schema::table('training_programs', function (Blueprint $table) {
                $table->unique(['tenant_id', 'code'], 'training_programs_tenant_id_code_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('training_programs')) {
            return;
        }

        if (Schema::hasIndex('training_programs', 'training_programs_tenant_id_code_unique')) {
            Schema::table('training_programs', function (Blueprint $table) {
                $table->dropUnique('training_programs_tenant_id_code_unique');
            });
        }

        Schema::table('training_programs', function (Blueprint $table) {
            foreach (['code', 'banner_image_path', 'allow_school_nomination'] as $col) {
                if (Schema::hasColumn('training_programs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
