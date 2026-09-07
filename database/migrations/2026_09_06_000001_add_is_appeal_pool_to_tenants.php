<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a school-type tenant as the one placeholder "Appeal School" a Sahodaya
 * files wildcard-appeal registrations under, so championship aggregation and any
 * school-listing UI can exclude it by flag rather than by name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'is_appeal_pool')) {
                $table->boolean('is_appeal_pool')->default(false)->after('is_non_affiliated');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'is_appeal_pool')) {
                $table->dropColumn('is_appeal_pool');
            }
        });
    }
};
