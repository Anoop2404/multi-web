<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sahodaya_profiles')) {
            return;
        }

        Schema::table('sahodaya_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('sahodaya_profiles', 'allow_non_affiliated_schools')) {
                $table->boolean('allow_non_affiliated_schools')->default(false)->after('fixed_membership_fee_amount');
            }
            if (! Schema::hasColumn('sahodaya_profiles', 'non_affiliated_membership_fee_type')) {
                $table->string('non_affiliated_membership_fee_type', 32)->default('fixed')->after('allow_non_affiliated_schools');
            }
            if (! Schema::hasColumn('sahodaya_profiles', 'non_affiliated_fixed_membership_fee_amount')) {
                $table->decimal('non_affiliated_fixed_membership_fee_amount', 10, 2)->nullable()->after('non_affiliated_membership_fee_type');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sahodaya_profiles')) {
            return;
        }

        Schema::table('sahodaya_profiles', function (Blueprint $table) {
            foreach ([
                'non_affiliated_fixed_membership_fee_amount',
                'non_affiliated_membership_fee_type',
                'allow_non_affiliated_schools',
            ] as $col) {
                if (Schema::hasColumn('sahodaya_profiles', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
