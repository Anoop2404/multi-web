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
            if (! Schema::hasColumn('training_programs', 'require_verified_teachers')) {
                $table->boolean('require_verified_teachers')->default(false)->after('qr_registration_enabled');
            }
            if (! Schema::hasColumn('training_programs', 'allow_school_attendance')) {
                $table->boolean('allow_school_attendance')->default(true)->after('require_verified_teachers');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('training_programs')) {
            return;
        }

        Schema::table('training_programs', function (Blueprint $table) {
            foreach (['require_verified_teachers', 'allow_school_attendance'] as $col) {
                if (Schema::hasColumn('training_programs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
