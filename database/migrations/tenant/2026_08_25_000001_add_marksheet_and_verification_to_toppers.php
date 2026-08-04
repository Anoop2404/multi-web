<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('toppers')) {
            Schema::table('toppers', function (Blueprint $table) {
                if (! Schema::hasColumn('toppers', 'marksheet_path')) {
                    $table->string('marksheet_path', 255)->nullable()->after('photo');
                }
                if (! Schema::hasColumn('toppers', 'marksheet_disk')) {
                    $table->string('marksheet_disk', 50)->nullable()->after('marksheet_path');
                }
                if (! Schema::hasColumn('toppers', 'verification_status')) {
                    $table->string('verification_status', 30)->default('pending')->after('marksheet_disk');
                }
                if (! Schema::hasColumn('toppers', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('verification_status');
                }
                if (! Schema::hasColumn('toppers', 'verified_at')) {
                    $table->timestamp('verified_at')->nullable()->after('rejection_reason');
                }
                if (! Schema::hasColumn('toppers', 'verified_by')) {
                    $table->string('verified_by', 100)->nullable()->after('verified_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('toppers')) {
            Schema::table('toppers', function (Blueprint $table) {
                $cols = array_filter([
                    'marksheet_path',
                    'marksheet_disk',
                    'verification_status',
                    'rejection_reason',
                    'verified_at',
                    'verified_by',
                ], fn ($c) => Schema::hasColumn('toppers', $c));

                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
