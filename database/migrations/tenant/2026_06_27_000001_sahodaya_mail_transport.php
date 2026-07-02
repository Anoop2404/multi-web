<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sahodaya_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('sahodaya_profiles', 'mail_transport')) {
                $table->string('mail_transport', 20)->default('zeptomail_api')->after('active_academic_year');
            }
            if (! Schema::hasColumn('sahodaya_profiles', 'zeptomail_region')) {
                $table->string('zeptomail_region', 10)->default('in')->after('mail_transport');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sahodaya_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('sahodaya_profiles', 'zeptomail_region')) {
                $table->dropColumn('zeptomail_region');
            }
            if (Schema::hasColumn('sahodaya_profiles', 'mail_transport')) {
                $table->dropColumn('mail_transport');
            }
        });
    }
};
