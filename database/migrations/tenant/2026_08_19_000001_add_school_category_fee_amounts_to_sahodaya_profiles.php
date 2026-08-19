<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('sahodaya_profiles')) {
            Schema::table('sahodaya_profiles', function (Blueprint $table) {
                if (! Schema::hasColumn('sahodaya_profiles', 'school_category_fee_amounts')) {
                    $table->json('school_category_fee_amounts')->nullable()->after('fixed_membership_fee_amount');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sahodaya_profiles')) {
            Schema::table('sahodaya_profiles', function (Blueprint $table) {
                if (Schema::hasColumn('sahodaya_profiles', 'school_category_fee_amounts')) {
                    $table->dropColumn('school_category_fee_amounts');
                }
            });
        }
    }
};
