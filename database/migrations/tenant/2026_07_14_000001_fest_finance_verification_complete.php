<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sahodaya_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('sahodaya_profiles', 'require_student_verification')) {
                $table->boolean('require_student_verification')->default(true)->after('student_edit_lock_at');
            }
        });

        Schema::table('fest_item_heads', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_item_heads', 'default_item_fee')) {
                $table->decimal('default_item_fee', 10, 2)->nullable()->after('sort_order');
            }
            if (! Schema::hasColumn('fest_item_heads', 'extra_item_fee')) {
                $table->decimal('extra_item_fee', 10, 2)->nullable()->after('default_item_fee');
            }
        });

        Schema::table('fest_event_items', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_event_items', 'competition_start')) {
                $table->date('competition_start')->nullable()->after('reg_end');
            }
            if (! Schema::hasColumn('fest_event_items', 'competition_end')) {
                $table->date('competition_end')->nullable()->after('competition_start');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sahodaya_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('sahodaya_profiles', 'require_student_verification')) {
                $table->dropColumn('require_student_verification');
            }
        });

        Schema::table('fest_item_heads', function (Blueprint $table) {
            foreach (['default_item_fee', 'extra_item_fee'] as $col) {
                if (Schema::hasColumn('fest_item_heads', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('fest_event_items', function (Blueprint $table) {
            foreach (['competition_start', 'competition_end'] as $col) {
                if (Schema::hasColumn('fest_event_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
