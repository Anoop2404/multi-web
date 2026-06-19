<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sahodaya_profiles', function (Blueprint $table) {
            $table->json('application_form_config')->nullable()->after('payment_upi');
        });
    }

    public function down(): void
    {
        Schema::table('sahodaya_profiles', function (Blueprint $table) {
            $table->dropColumn('application_form_config');
        });
    }
};
