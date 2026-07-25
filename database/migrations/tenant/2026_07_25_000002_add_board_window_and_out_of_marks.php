<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sahodaya_registration_windows')) {
            Schema::table('sahodaya_registration_windows', function (Blueprint $table) {
                if (! Schema::hasColumn('sahodaya_registration_windows', 'board_entry_starts_at')) {
                    $table->date('board_entry_starts_at')->nullable()->after('registration_ends_at');
                }
                if (! Schema::hasColumn('sahodaya_registration_windows', 'board_entry_ends_at')) {
                    $table->date('board_entry_ends_at')->nullable()->after('board_entry_starts_at');
                }
                if (! Schema::hasColumn('sahodaya_registration_windows', 'default_total_marks')) {
                    $table->integer('default_total_marks')->nullable()->default(500)->after('board_entry_ends_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sahodaya_registration_windows')) {
            Schema::table('sahodaya_registration_windows', function (Blueprint $table) {
                $cols = array_filter([
                    Schema::hasColumn('sahodaya_registration_windows', 'board_entry_starts_at') ? 'board_entry_starts_at' : null,
                    Schema::hasColumn('sahodaya_registration_windows', 'board_entry_ends_at') ? 'board_entry_ends_at' : null,
                    Schema::hasColumn('sahodaya_registration_windows', 'default_total_marks') ? 'default_total_marks' : null,
                ]);
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
