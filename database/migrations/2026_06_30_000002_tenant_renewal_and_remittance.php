<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'renewal_status')) {
                $table->string('renewal_status', 20)->nullable()->after('membership_status');
            }
        });

        Schema::table('state_remittances', function (Blueprint $table) {
            if (! Schema::hasColumn('state_remittances', 'source_breakdown')) {
                $table->json('source_breakdown')->nullable()->after('amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'renewal_status')) {
                $table->dropColumn('renewal_status');
            }
        });

        Schema::table('state_remittances', function (Blueprint $table) {
            if (Schema::hasColumn('state_remittances', 'source_breakdown')) {
                $table->dropColumn('source_breakdown');
            }
        });
    }
};
